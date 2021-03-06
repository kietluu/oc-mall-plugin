<?php

namespace OFFLINE\Mall\Classes\Registration;

use Hashids\Hashids;
use Illuminate\Support\Facades\Cache;
use OFFLINE\Mall\Classes\Customer\AuthManager;
use OFFLINE\Mall\Classes\Customer\DefaultSignInHandler;
use OFFLINE\Mall\Classes\Customer\DefaultSignUpHandler;
use OFFLINE\Mall\Classes\Customer\SignInHandler;
use OFFLINE\Mall\Classes\Customer\SignUpHandler;
use OFFLINE\Mall\Classes\Index\Filebase;
use OFFLINE\Mall\Classes\Index\Index;
use OFFLINE\Mall\Classes\Index\IndexNotSupportedException;
use OFFLINE\Mall\Classes\Index\MySQL\MySQL;
use OFFLINE\Mall\Classes\Payments\DefaultPaymentGateway;
use OFFLINE\Mall\Classes\Payments\Offline;
use OFFLINE\Mall\Classes\Payments\PaymentGateway;
use OFFLINE\Mall\Classes\Payments\PayPalRest;
use OFFLINE\Mall\Classes\Payments\Stripe;
use OFFLINE\Mall\Classes\Utils\DefaultMoney;
use OFFLINE\Mall\Classes\Utils\Money;
use OFFLINE\Mall\Models\GeneralSettings;

trait BootServiceContainer
{
    protected function registerServices()
    {
        $this->app->bind(SignInHandler::class, function () {
            return new DefaultSignInHandler();
        });
        $this->app->bind(SignUpHandler::class, function () {
            return new DefaultSignUpHandler();
        });
        $this->app->singleton(Money::class, function () {
            return new DefaultMoney();
        });
        $this->app->singleton(PaymentGateway::class, function () {
            $gateway = new DefaultPaymentGateway();
            $gateway->registerProvider(new Offline());
            $gateway->registerProvider(new PayPalRest());
            $gateway->registerProvider(new Stripe());

            return $gateway;
        });
        $this->app->singleton(Hashids::class, function () {
            return new Hashids(config('app.key', 'oc-mall'), 8);
        });
        $this->app->singleton('user.auth', function () {
            return AuthManager::instance();
        });

        $this->app->bind(Index::class, function () {

            $driver = Cache::rememberForever('offline_mall.mysql.index.driver', function () {
                $driver = GeneralSettings::get('index_driver');
                if ($driver === null) {
                    GeneralSettings::set('index_driver', 'database');
                }

                return $driver;
            });

            try {
                if ($driver === 'filesystem') {
                    return new Filebase();
                }

                return new MySQL();
            } catch (IndexNotSupportedException $e) {
                logger()->error(
                    '[OFFLINE.Mall] Your database does not support JSON data. Your index driver has been switched to "Filesystem". Update your database to make use of database indexing.'
                );
                GeneralSettings::set('index_driver', 'filesystem');
                Cache::forget('offline_mall.mysql.index.driver');

                return new Filebase();
            }
        });
    }
}
