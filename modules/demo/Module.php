<?php

namespace modules\demo;

use Craft;
use yii\base\Module as BaseModule;

/**
 * The demo's own commands: `php craft demo/content`.
 */
class Module extends BaseModule
{
    public function init(): void
    {
        Craft::setAlias('@modules/demo', __DIR__);

        if (Craft::$app->getRequest()->getIsConsoleRequest()) {
            $this->controllerNamespace = 'modules\\demo\\console\\controllers';
        }

        parent::init();
    }
}
