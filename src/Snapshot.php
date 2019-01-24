<?php
/**
 * Snapshot plugin for Craft CMS 3.x
 *
 * Snapshot or PDF generation from a url or a html page.
 *
 * @link      https://enupal.com
 * @copyright Copyright (c) 2018 Enupal
 */

namespace enupal\snapshot;

use craft\helpers\UrlHelper;
use enupal\snapshot\services\App;
use enupal\snapshot\variables\SnapshotVariable;
use enupal\snapshot\models\Settings;

use Craft;
use craft\base\Plugin;
use craft\web\twig\variables\CraftVariable;
use enupal\stripe\events\NotificationEvent;
use enupal\stripe\services\Emails;

use enupal\stripe\Stripe;
use yii\base\Event;

/**
 * Class Snapshot
 *
 * @author    Enupal
 * @package   Snapshot
 * @since     1.0.0
 *
 * @property  Snapshot $snapshot
 */
class Snapshot extends Plugin
{
    // Static Properties
    // =========================================================================

    /**
     * @var App
     */
    public static $app;

    /**
     * @inheritdoc
     */
    public $schemaVersion = '1.2.0';

    /**
     * @inheritdoc
     */
    public $hasCpSection = false;

    /**
     * @inheritdoc
     */
    public $hasCpSettings = true;

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        self::$app = $this->get('app');

        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function(Event $event) {
                /** @var CraftVariable $variable */
                $variable = $event->sender;
                $variable->set('enupalsnapshot', SnapshotVariable::class);
            }
        );

        $stripePayments = Craft::$app->getPlugins()->getPlugin('enupal-stripe');

        if ($stripePayments){
            Craft::$app->view->hook('cp.enupal-stripe.order.actionbutton', function(array &$context) {
                $order = $context['order'];
                $settings = $this->getStripePaymentsSettings();
                $view = Craft::$app->getView();
                $view->setTemplatesPath(Craft::$app->path->getSiteTemplatesPath());
                $pdfUrl = Snapshot::$app->pdf->displayOrder($order, $settings);
                $view->setTemplatesPath(Craft::$app->path->getCpTemplatesPath());

                return $view->renderTemplate('enupal-snapshot/_pdfbuttons/stripepayments', ['pdfUrl' => $pdfUrl]);
            });

            Event::on(Emails::class, Emails::EVENT_BEFORE_SEND_NOTIFICATION_EMAIL, function(NotificationEvent $e) {
                $message = $e->message;
                $settings = $this->getStripePaymentsSettings();

                if (isset($e->order) && $e->type == Stripe::$app->emails::CUSTOMER_TYPE){
                    $view = Craft::$app->getView();
                    $view->setTemplatesPath(Craft::$app->path->getSiteTemplatesPath());
                    $pdfUrl = Snapshot::$app->pdf->displayOrder($e->order, $settings);
                    $view->setTemplatesPath(Craft::$app->path->getCpTemplatesPath());
                    if (UrlHelper::isFullUrl($pdfUrl)){
                        $pdfUrl = UrlHelper::siteUrl($pdfUrl);
                    }
                    $content = file_get_contents($pdfUrl);
                    $path = parse_url($pdfUrl, PHP_URL_PATH);
                    $fileName = basename($path);

                    if ($content){
                        $message->attachContent($content, ['fileName' => $fileName, 'contentType' => 'application/pdf']);
                    }
                }
            });
        }
    }

    // Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected function createSettingsModel()
    {
        return new Settings();
    }

    /**
     * @inheritdoc
     */
    protected function settingsHtml(): string
    {
        return Craft::$app->view->renderTemplate(
            'enupal-snapshot/settings',
            [
                'settings' => $this->getSettings()
            ]
        );
    }

    /**
     * @throws \Throwable
     */
    protected function afterInstall()
    {
        self::$app->snapshots->installDefaultVolume();
    }

    /**
     * @param string $message
     * @param array $params
     *
     * @return string
     */
    public static function t($message, array $params = [])
    {
        return Craft::t('enupal-snapshot', $message, $params);
    }

    /**
     * @param        $message
     * @param string $type
     */
    public static function log($message, $type = 'info')
    {
        Craft::$type(self::t($message), __METHOD__);
    }

    /**
     * @param $message
     */
    public static function info($message)
    {
        Craft::info(self::t($message), __METHOD__);
    }

    /**
     * @param $message
     */
    public static function error($message)
    {
        Craft::error(self::t($message), __METHOD__);
    }

    /**
     * @return array
     */
    private function getStripePaymentsSettings()
    {
        $settings = [
            'inline' => false,
            'overrideFile' => false,
            'cliOptions' => [
                'viewport-size' => '1280x1024',
                'margin-top' => 0,
                'margin-bottom' => 0,
                'margin-left' => 0,
                'margin-right' => 0
            ]
        ];

        return $settings;
    }
}
