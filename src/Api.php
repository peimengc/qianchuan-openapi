<?php


namespace Peimengc\QianchuanOpenapi;


use GuzzleHttp\Client;

class Api
{
    protected $appid;
    protected $secret;
    protected $baseUri = 'https://ad.oceanengine.com';
    protected $accessToken;

    public function __construct($appid, $secret)
    {
        $this->appid = $appid;
        $this->secret = $secret;
    }

    public function getHttpClient()
    {
        return new Client($this->getGuzzleOptions());
    }

    public function getGuzzleOptions()
    {
        return array_merge([
            'base_uri' => $this->baseUri,
        ], $this->guzzleOptions);
    }

    public function setAccessToken($accessToken)
    {
        $this->accessToken = $accessToken;
        return $this;
    }

    public function setGuzzleOptions(array $options)
    {
        $this->guzzleOptions = $options;
        return $this;
    }

    protected function httpGet($url, array $data = [])
    {
        $data['access_token'] = $this->accessToken;
        return $this->request('GET', $url, ['query' => $data]);
    }

    public function httpJson($url, array $data = [])
    {
        $headers = [
            'Access-Token' => $this->accessToken,
            'Content-Type' => 'application/json'
        ];
        return $this->request('POST', $url, ['json' => $data, 'headers' => $headers]);
    }

    public function request($method, $url, array $options)
    {
        $contents = $this->getHttpClient()->request($method, $url, $options)->getBody()->getContents();
        return json_decode($contents, true);
    }

    public function getAuthUrl($redirectUri, $state, $scope)
    {
        $query = [
            'app_id' => $this->appid,
            'state' => $state,
            'scope' => $scope,
            'material_auth' => 1,
            'redirect_uri' => $redirectUri,
        ];
        return 'https://qianchuan.jinritemai.com/openapi/qc/audit/oauth.html?' . http_build_query($query);
    }

    public function getAccessToken($code)
    {
        return $this->httpJson('/open_api/oauth2/access_token/', [
            'app_id' => $this->appid,
            'secret' => $this->secret,
            'grant_type' => 'auth_code',
            'auth_code' => $code
        ]);
    }

    public function refreshToken($refresh_token)
    {
        return $this->httpJson('/open_api/oauth2/access_token/', [
            'app_id' => $this->appid,
            'secret' => $this->secret,
            'grant_type' => 'refresh_token',
            'refresh_token' => $refresh_token
        ]);
    }

    //获取已授权的账户（店铺/代理商）
    public function getAdvertiser()
    {
        return $this->httpGet('/open_api/oauth2/advertiser/get/', [
            'access_token' => $this->accessToken,
            'app_id' => $this->appid,
            'secret' => $this->secret,
        ]);
    }

    //获取店铺账户关联的广告账户列表
    public function getShopAdvertisers($shopId, $page = 1, $pageSize = 100)
    {
        if ($pageSize > 100) {
            throw new InvalidArgumentException('获取店铺账户关联的广告账户列表，page_size最大值：100');
        }
        return $this->request('GET', '/open_api/v1.0/qianchuan/shop/advertiser/list/', [
            'headers' => [
                'Access-Token' => $this->accessToken,
            ],
            'query' => [
                'shop_id' => $shopId,
                'page' => $page,
                'page_size' => $pageSize,
            ]
        ]);
    }

    //更新计划状态
    public function adStatusUpdate($adIds, $advertiserId, $optStatus)
    {
        if (count($adIds)) {
            throw new InvalidArgumentException('更新计划状态，一次最多可以处理10个计划');
        }
        return $this->httpJson('/open_api/v1.0/qianchuan/ad/status/update/', [
            'ad_ids' => $adIds,
            'advertiser_id' => $advertiserId,
            'opt_status' => $optStatus,
        ]);
    }

    //更新计划状态
    public function adBudgetUpdate($advertiserId, array $data)
    {
        if (count($data)) {
            throw new InvalidArgumentException('更新广告计划的预算，一次最多可以处理10个计划');
        }
        return $this->httpJson('/open_api/v1.0/qianchuan/ad/budget/update/', [
            'advertiser_id' => $advertiserId,
            'data' => $data
        ]);
    }
}