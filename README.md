# Dog-ear Books: a Reviews demo for Craft

A made-up independent bookshop for trying [Reviews for Craft](https://github.com/1994limited/reviews-craft): ten books as Craft Commerce products and two journal entries, with sixty reviews:

- an average, star breakdown and criteria (Story, Writing) on each book;
- replies from the shop, "helpful" votes, and a customer's verified review;
- reviews waiting for an editor, one rejected and one marked as spam, to moderate in the control panel;
- the ready-made reviews section and its default styles (`templates/books/_product.twig`), and review schema on each book's page;
- two reviews imported from Trustpilot (on Coral Arithmetic), which say where they came from and don't count towards the average, and a Feefo sync source, switched off so the demo never calls Feefo.

## Running it with Herd or Valet

Make a MySQL database called `reviews_craft_demo`, then:

```bash
composer install
cp .env.example .env
php craft setup/keys
php craft install --username=admin --email=admin@example.com --password=password --site-name="Dog-ear Books" --site-url='$PRIMARY_SITE_URL' --language=en-GB --interactive=0
php craft demo/content
```

Or with DDEV: `ddev start`, `ddev composer install`, then the same commands with `ddev craft`.

Sign in at `/admin` with the username and password you gave `craft install`. The ones above are for a local copy only.

`php craft demo/content --fresh` makes the reviews again.

## Trying it

1. Open a book, such as **Notes from a Lighthouse**, and write a review. It waits for an editor.
2. In the control panel, **Reviews → Waiting for moderation**: approve it, and reply.
3. Back on the book's page, it's there with the reply, and the average has changed.
4. Under **Reviews → Settings**, try reviews from signed-in users only, photos, a captcha, or publishing verified buyers' reviews straight away.
5. Under **Reviews → Import**, upload `scripts/sample-export.csv` (a Yotpo export) to see what would be added before anything is.
6. Jo (`jo@example.com`) bought two books. Set Jo a password under **Users**, sign in as Jo, and their review is marked as a verified buyer's.

## Screenshots

`node scripts/screenshots.mjs` takes the screenshots for Reviews' docs (after `npm install`). See the script for its settings.

The books, their reviews and their reviewers are made up.
