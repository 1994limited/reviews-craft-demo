<?php

namespace modules\demo\console\controllers;

use Craft;
use craft\commerce\elements\Order;
use craft\commerce\elements\Product;
use craft\commerce\elements\Variant;
use craft\commerce\models\ProductType;
use craft\commerce\models\ProductTypeSite;
use craft\commerce\Plugin as Commerce;
use craft\console\Controller;
use craft\elements\Entry;
use craft\elements\User;
use craft\helpers\Console;
use craft\helpers\StringHelper;
use DateTime;
use nineteenninetyfour\reviews\elements\Review;
use nineteenninetyfour\reviews\elements\ReviewStatus;
use nineteenninetyfour\reviews\Plugin as Reviews;
use RuntimeException;
use yii\console\ExitCode;

/**
 * Dog-ear Books: ten books as Commerce products, two journal entries, a
 * customer with an order, and reviews of them all. `--fresh` makes the
 * reviews again.
 */
class ContentController extends Controller
{
    public $defaultAction = 'index';

    public bool $fresh = false;

    protected const BOOKS = [
        ['Notes from a Lighthouse', 10.00],
        ['Coral Arithmetic', 13.00],
        ['Moss on the Northern Wall', 11.50],
        ['Ninety Blue Kites', 11.00],
        ['A Field Guide to Leaving', 12.00],
        ['Seven Ways to Boil the Sea', 12.00],
        ['The Lantern Ledger', 9.99],
        ['The Paper Cartographer', 16.00],
        ['Small Weathers', 12.50],
        ['The Quiet Apprentice', 15.50],
    ];

    protected const PEOPLE = ['Ada Lovelace', 'Grace Hopper', 'Alan Turing', 'Katherine Johnson', 'Mary Somerville', 'Hedy Lamarr', 'Tim Berners-Lee', 'Radia Perlman', 'Joan Clarke', 'Dorothy Vaughan', 'Annie Easley', 'Karen Spärck Jones', 'Margaret Hamilton', 'Charles Babbage'];

    protected const GOOD = [
        ['Couldn’t put it down', 'Read it in two sittings. The ending caught me completely off guard, in the best way.'],
        ['A quiet wonder', "Gentle, strange and very funny in places.\n\nI’ve already bought a second copy for my sister."],
        ['Beautifully written', 'Every page has a sentence I wanted to underline.'],
        ['Perfect for a long train journey', 'Short chapters, lovely pace. The paperback is a nice size for a coat pocket too.'],
        ['Book club loved it', 'Eight of us, and for once we all agreed. Lots to talk about.'],
    ];

    protected const MIDDLING = [
        ['Good, not great', 'The middle third drags a little, but it picks up again.'],
        ['Worth reading', 'Not quite as good as the reviews made out, but I’m glad I read it.'],
    ];

    protected const POOR = [
        ['Not for me', 'I wanted to like it more than I did. The characters never quite came alive for me.'],
        ['Arrived damaged', 'The story was fine but the cover was bent when it arrived.'],
    ];

    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), ['fresh']);
    }

    public function actionIndex(): int
    {
        $reviews = Reviews::getInstance()->reviews;

        if ($this->fresh) {
            foreach (Review::find()->status(null)->all() as $review) {
                $reviews->delete($review);
            }
        }

        $products = $this->products();
        $entries = $this->journal();
        $this->settings();
        [$jo, $order] = $this->customer($products);

        if (Review::find()->status(null)->exists()) {
            $this->stdout("The reviews are there already. Run `php craft demo/content --fresh` to make them again.\n");

            return ExitCode::OK;
        }

        $made = $this->reviews([...$products, ...$entries], $products, $jo, $order);
        $made += $this->elsewhere();

        $this->stdout("Made {$made} reviews of ".count($products).' books and '.count($entries)." journal entries.\n", Console::FG_GREEN);
        $this->stdout("Jo (jo@example.com) bought \"{$products[0]->title}\" and \"{$products[1]->title}\": their review of the first is verified.\n");

        return ExitCode::OK;
    }

    /**
     * Jo's review's ID, for the screenshots.
     */
    public function actionJoReviewId(): int
    {
        $this->stdout(Review::find()->email('jo@example.com')->one()?->id."\n");

        return ExitCode::OK;
    }

    /**
     * @return array<int, Product>
     */
    protected function products(): array
    {
        $site = Craft::$app->getSites()->getPrimarySite();
        $types = Commerce::getInstance()->getProductTypes();
        $type = $types->getProductTypeByHandle('books');

        if (! $type) {
            $type = new ProductType(['name' => 'Books', 'handle' => 'books', 'maxVariants' => 1]);
            $type->setSiteSettings([$site->id => new ProductTypeSite(['siteId' => $site->id, 'hasUrls' => true, 'uriFormat' => 'books/{slug}', 'template' => 'books/_product'])]);
            $this->save($type, fn () => $types->saveProductType($type));
        }

        $products = [];

        foreach (self::BOOKS as $i => [$title, $price]) {
            $slug = StringHelper::slugify($title);
            $product = Product::find()->slug($slug)->status(null)->one();

            if (! $product) {
                $product = new Product(['typeId' => $type->id, 'title' => $title, 'slug' => $slug]);
                $this->save($product);

                $variant = new Variant(['ownerId' => $product->id, 'primaryOwnerId' => $product->id, 'title' => $title, 'sku' => 'DOG-'.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT), 'basePrice' => $price, 'isDefault' => true]);
                $this->save($variant);
                $product = Product::find()->id($product->id)->one();
            }

            $products[] = $product;
        }

        return $products;
    }

    /**
     * @return array<int, Entry>
     */
    protected function journal(): array
    {
        $section = Craft::$app->getEntries()->getSectionByHandle('journal');
        $entries = [];

        foreach (['Our autumn reading list', 'Ten years of Dog-ear Books'] as $title) {
            $entry = Entry::find()->section('journal')->title($title)->one();

            if (! $entry) {
                $entry = new Entry(['sectionId' => $section->id, 'typeId' => $section->getEntryTypes()[0]->id, 'title' => $title, 'postDate' => new DateTime('-30 days')]);
                $this->save($entry);
            }

            $entries[] = $entry;
        }

        return $entries;
    }

    protected function settings(): void
    {
        $plugin = Reviews::getInstance();

        Craft::$app->getPlugins()->savePluginSettings($plugin, array_merge($plugin->getSettings()->toArray(), [
            'sections' => ['journal'],
            'productTypes' => ['books'],
            'criteria' => [['handle' => 'story', 'label' => 'Story'], ['handle' => 'writing', 'label' => 'Writing']],
            'photosVolume' => 'images',
            'requestsEnabled' => true,
            'requestsDelayDays' => 14,
        ]));
    }

    /**
     * Jo, a customer, and their order of two books.
     *
     * @param  array<int, Product>  $products
     * @return array{User, Order}
     */
    protected function customer(array $products): array
    {
        $jo = User::find()->email('jo@example.com')->status(null)->one();

        if (! $jo) {
            $jo = new User(['username' => 'jo', 'email' => 'jo@example.com', 'fullName' => 'Jo Reader']);
            $this->save($jo);
            Craft::$app->getUsers()->activateUser($jo);
        }

        $order = Order::find()->email('jo@example.com')->isCompleted()->one();

        if (! $order) {
            $order = new Order(['number' => Commerce::getInstance()->getCarts()->generateCartNumber()]);
            $order->setCustomer($jo);
            $order->setEmail($jo->email);

            foreach (array_slice($products, 0, 2) as $product) {
                $order->addLineItem(Commerce::getInstance()->getLineItems()->create($order, ['purchasableId' => $product->getDefaultVariant()->id, 'qty' => 1]));
            }

            $this->save($order);
            $order->markAsComplete();
        }

        return [$jo, $order];
    }

    /**
     * @param  array<int, \craft\base\ElementInterface>  $elements
     * @param  array<int, Product>  $products
     */
    protected function reviews(array $elements, array $products, User $jo, Order $order): int
    {
        $reviews = Reviews::getInstance()->reviews;
        $admin = User::find()->admin()->one();
        $made = 0;

        mt_srand(1994);

        foreach ($elements as $i => $element) {
            $count = [9, 6, 4, 7, 3, 5, 2, 8, 0, 4, 3, 2][$i] ?? 0;

            for ($n = 0; $n < $count; $n++) {
                $roll = mt_rand(1, 100);
                [$rating, $pool] = $roll > 35 ? [mt_rand(4, 5), self::GOOD] : ($roll > 12 ? [3, self::MIDDLING] : [mt_rand(1, 2), self::POOR]);
                [$title, $body] = $pool[array_rand($pool)];

                $review = $this->review($element, $rating, $title, $body, self::PEOPLE[($i * 3 + $n) % count(self::PEOPLE)], mt_rand(2, 120), [
                    'ratings' => ['story' => max(1, min(5, $rating + mt_rand(-1, 1))), 'writing' => max(1, min(5, $rating + mt_rand(-1, 0)))],
                ]);
                $reviews->approve($review);

                for ($v = mt_rand(0, 100) > 60 ? mt_rand(1, 14) : 0; $v > 0; $v--) {
                    $reviews->vote($review, 'demo-'.$review->id.'-'.$v);
                }

                $made++;
            }
        }

        foreach (Review::find()->rating([1, 2])->limit(2)->all() as $review) {
            $reviews->reply($review, 'Sorry to hear that. Thank you for being honest: we’ve passed this on, and if your copy arrived damaged, reply to your order email and we’ll send another.', $admin);
        }

        // Jo's verified review of a book they bought.
        $joReview = $this->review($products[0], 5, 'Read it on the move, then again at home', "Forty chapters, each the length of a bus ride. I bought the hardback and it's beautifully made.\n\nThe chapter about leaving a flat for the last time had me in tears.", 'Jo Reader', 18, ['email' => 'jo@example.com', 'userId' => $jo->id, 'verified' => true, 'orderId' => $order->id]);
        $reviews->approve($joReview);
        $reviews->reply($joReview, 'Thank you, Jo. We cried at that chapter too.', $admin);

        for ($v = 1; $v <= 23; $v++) {
            $reviews->vote($joReview, 'demo-jo-'.$v);
        }

        $made++;

        // Waiting for an editor, and the ones an editor turned down.
        $this->review($products[0], 4, 'A slow start, then wonderful', "Give it fifty pages.\nYou won’t regret it.", 'Radia Perlman', 1);
        $this->review($products[3], 2, 'Too long', 'Could have lost a hundred pages.', 'Charles Babbage', 0);
        $this->review($products[1], 5, null, null, 'Hedy Lamarr', 0);
        $reviews->reject($this->review($products[4], 1, 'Wrong book', 'This review is about a different book entirely.', 'Ben Mistake', 6), 'About a different book.');
        $reviews->spam($this->review($products[5], 5, 'Cheap watches', 'Best prices on watches at www.watches-cheap.example', 'Totally Real', 3));

        return $made + 5;
    }

    /**
     * Reviews from elsewhere: two Trustpilot reviews imported from an export
     * (they show "From Trustpilot", and don't count towards the average), and
     * a Feefo sync source, switched off so the demo never calls Feefo.
     */
    protected function elsewhere(): int
    {
        $plugin = Reviews::getInstance();

        $csv = "Review Id,Review Created (UTC),Review Stars,Review Title,Review Content,Consumer Name,Review URL,Product SKU\n"
            ."demo-tp-1,2026-09-12T10:00:00Z,5,Arrived beautifully wrapped,\"Ordered for my dad's birthday. It came in two days, wrapped in brown paper and string.\",Priya Shah,https://uk.trustpilot.com/reviews/demo-tp-1,DOG-002\n"
            ."demo-tp-2,2026-08-30T09:30:00Z,4,Lovely shop,\"Good choice of books, and a handwritten note in the parcel.\",Tom Ellery,https://uk.trustpilot.com/reviews/demo-tp-2,DOG-002\n";

        $report = $plugin->imports->import($plugin->imports->read($csv)->reviews, new \nineteenninetyfour\reviews\models\ImportOptions);

        $sources = $plugin->sync;
        $exists = array_filter($sources->all(), fn ($source) => $source->type === 'feefo');

        if (! $exists) {
            $source = new \nineteenninetyfour\reviews\models\Source([
                'type' => 'feefo',
                'name' => 'Feefo',
                'settings' => ['merchant' => 'dog-ear-books'],
                'enabled' => false,
            ]);
            $sources->save($source) || throw new RuntimeException('Couldn’t save the Feefo source: '.json_encode($source->getErrors()));
        }

        return $report->added;
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    protected function review(\craft\base\ElementInterface $element, int $rating, ?string $title, ?string $body, string $name, int $daysAgo, array $extra = []): Review
    {
        $review = new Review([
            'elementId' => $element->id,
            'reviewSiteId' => Craft::$app->getSites()->getPrimarySite()->id,
            'rating' => $rating,
            'title' => $title,
            'body' => $body,
            'name' => $name,
            'email' => strtolower(str_replace(' ', '.', (string) iconv('UTF-8', 'ASCII//TRANSLIT', $name))).'@example.com',
            'reviewStatus' => ReviewStatus::Pending,
            'dateCreated' => new DateTime("-{$daysAgo} days"),
            ...$extra,
        ]);

        $this->save($review, fn () => Reviews::getInstance()->reviews->save($review, false));

        return $review;
    }

    protected function save(object $model, ?callable $save = null): void
    {
        $saved = $save ? $save() : Craft::$app->getElements()->saveElement($model);

        if (! $saved) {
            throw new RuntimeException('Couldn’t save '.get_class($model).': '.json_encode($model->getErrors()));
        }
    }
}
