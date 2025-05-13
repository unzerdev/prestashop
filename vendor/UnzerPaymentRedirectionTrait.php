<?php
/**
 * 2007-2024 patworx.de
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade the plugin to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 *  @author    patworx multimedia GmbH <service@patworx.de>
 *  @copyright 2007-2024 patworx multimedia GmbH
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

trait UnzerPaymentRedirectionTrait
{

    /**
     * @return array
     */
    public static function getServerVar()
    {
        return $_SERVER;
    }

    /**
     * @param $controller
     */
    public function PrestaShopNotificationsFetcher($controller)
    {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }

        if (session_status() == PHP_SESSION_ACTIVE && isset($_SESSION['notifications'])) {
            $notifications = json_decode($_SESSION['notifications'], true);
            unset($_SESSION['notifications']);
        } elseif (isset($_COOKIE['notifications'])) {
            $notifications = json_decode($_COOKIE['notifications'], true);
            unset($_COOKIE['notifications']);
        }
        if (isset($notifications)) {
            $controller->errors = $notifications['error'];
            $controller->warning = $notifications['warning'];
            $controller->success = $notifications['success'];
            $controller->info = $notifications['info'];
        }
    }

    /**
     * @return false|mixed
     */
    public function PrestaShopRedirectWithNotifications($url)
    {
        $notifications = json_encode(array(
            'error' => $this->errors,
            'warning' => $this->warning,
            'success' => $this->success,
            'info' => $this->info,
        ));

        if (session_status() == PHP_SESSION_ACTIVE) {
            $_SESSION['notifications'] = $notifications;
        } elseif (session_status() == PHP_SESSION_NONE) {
            session_start();
            $_SESSION['notifications'] = $notifications;
        } else {
            setcookie('notifications', $notifications);
        }
        return \Tools::redirect($url);
    }

    /**
     * @return void
     */
    public function PrestaShopSetNotifications()
    {
        $notifications = json_encode(array(
            'error' => $this->errors,
            'warning' => $this->warning,
            'success' => $this->success,
            'info' => $this->info,
        ));

        if (session_status() == PHP_SESSION_ACTIVE) {
            $_SESSION['notifications'] = $notifications;
        } elseif (session_status() == PHP_SESSION_NONE) {
            session_start();
            $_SESSION['notifications'] = $notifications;
        } else {
            setcookie('notifications', $notifications);
        }
        return;
    }

}
