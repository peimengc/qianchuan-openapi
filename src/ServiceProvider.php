<?php


namespace Peimengc\QianchuanOpenapi;


class ServiceProvider extends \Illuminate\Support\ServiceProvider
{
    protected $defer = true;

    public function register()
    {
        $this->app->singleton(Api::class, function () {
            $app = $this->getApp();
            if (!isset($app['appid']) || !isset($app['secret'])) {
                throw new InvalidArgumentException("appid 和 secret 获取异常");
            }
            return new Api($app['appid'], $app['secret']);
        });

        $this->app->alias(Api::class, 'qianchuan-openapi');
    }

    protected function getApp()
    {
        $apps = config('services.qianchuan-openapi.apps', []);

        if (isset($_REQUEST['appid'])) {
            $apps = array_filter($apps, function ($app) {
                return $app['appid'] === $_REQUEST['appid'];
            });
        } else {
            uasort($apps, function () {
                return mt_rand() - mt_rand();
            });
        }

        return array_pop($apps);
    }

    public function provides()
    {
        return [Api::class, 'qianchuan-openapi'];
    }
}