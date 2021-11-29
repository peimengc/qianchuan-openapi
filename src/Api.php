<?php


namespace Peimengc\QianchuanOpenapi;


use GuzzleHttp\Client;

class Api
{
    protected $appid;
    protected $secret;
    protected $baseUri = 'https://ad.oceanengine.com';
    protected $accessToken;
    protected $guzzleOptions = [];

    public function __construct($appid, $secret)
    {
        $this->appid = $appid;
        $this->secret = $secret;
    }

    public function getAppid()
    {
        return $this->appid;
    }

    public function getSecret()
    {
        return $this->secret;
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
        return $this->request('GET', $url, ['query' => $data]);
    }

    public function httpJson($url, array $data = [])
    {
        return $this->request('POST', $url, ['json' => $data]);
    }

    public function request($method, $url, array $options)
    {
        $options['headers'] = [
            'Access-Token' => $this->accessToken,
            'Content-Type' => 'application/json'
        ];
        $contents = $this->getHttpClient()->request($method, $url, $options)->getBody()->getContents();
        $result = json_decode($contents, true);
        if ($result['code'] === 0) return $result;
        throw new ResponseContentsException($contents);
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
        $result = $this->httpJson('/open_api/oauth2/access_token/', [
            'app_id' => $this->appid,
            'secret' => $this->secret,
            'grant_type' => 'auth_code',
            'auth_code' => $code
        ]);
        $this->setAccessToken($result['data']['access_token']);
        return $result;
    }

    public function refreshToken($refresh_token)
    {
        return $this->httpJson('/open_api/oauth2/refresh_token/', [
            'app_id' => $this->appid,
            'secret' => $this->secret,
            'grant_type' => 'refresh_token',
            'refresh_token' => $refresh_token
        ]);
    }

    //获取授权时登录用户信息
    public function userInfo()
    {
        return $this->httpGet('/open_api/2/user/info/');
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
        return $this->httpGet('/open_api/v1.0/qianchuan/shop/advertiser/list/', [
            'shop_id' => $shopId,
            'page' => $page,
            'page_size' => $pageSize,
        ]);
    }

    //获取千川广告账户基础信息
    public function advertiserPublicInfo(array $advertiserIds)
    {
        if (count($advertiserIds) > 100) {
            throw new InvalidArgumentException('广告主ID，取值范围: 1-100');
        }
        return $this->httpGet('/open_api/2/advertiser/public_info/', [
            'advertiser_ids' => json_encode($advertiserIds),
        ]);
    }

    //更新计划状态
    public function adStatusUpdate($adIds, $advertiserId, $optStatus)
    {
        if (count($adIds) > 10 || count($adIds) < 1) {
            throw new InvalidArgumentException('更新计划状态，一次最多可以处理10个计划');
        }
        return $this->httpJson('/open_api/v1.0/qianchuan/ad/status/update/', [
            'ad_ids' => $adIds,
            'advertiser_id' => $advertiserId,
            'opt_status' => $optStatus,
        ]);
    }

    //更新计划预算
    public function adBudgetUpdate($advertiserId, array $data)
    {
        if (count($data) > 10 || count($data) < 1) {
            throw new InvalidArgumentException('更新广告计划的预算，一次最多可以处理10个计划');
        }
        return $this->httpJson('/open_api/v1.0/qianchuan/ad/budget/update/', [
            'advertiser_id' => $advertiserId,
            'data' => $data
        ]);
    }

    //更新计划出价
    public function adBidUpdate($advertiserId, array $data)
    {
        if (count($data) > 10 || count($data) < 1) {
            throw new InvalidArgumentException('更新广告计划的出价，一次最多可以处理10个计划');
        }
        return $this->httpJson('/open_api/v1.0/qianchuan/ad/bid/update/', [
            'advertiser_id' => $advertiserId,
            'data' => $data
        ]);
    }

    //获取计划详情
    public function adDetailGet($advertiserId, $adId)
    {
        return $this->httpGet('/open_api/v1.0/qianchuan/ad/detail/get/', [
            'advertiser_id' => $advertiserId,
            'ad_id' => $adId
        ]);
    }

    //获取计划列表
    public function adGet($advertiserId, array $filtering, $page, $pageSize = 1000)
    {
        if (!in_array($pageSize, [10, 20, 50, 100, 500, 1000])) {
            throw new InvalidArgumentException("page_size(页面大小),允许值：10, 20, 50, 100, 500, 1000");
        }
        if (!isset($filtering['marketing_goal']) || !in_array($filtering['marketing_goal'], ['VIDEO_PROM_GOODS', 'LIVE_PROM_GOODS'])) {
            throw new InvalidArgumentException("过滤条件filtering.marketing_goal(营销目标),必填,允许值：VIDEO_PROM_GOODS, LIVE_PROM_GOODS");
        }
        if (isset($filtering['ids']) && (count($filtering['ids']) > 100 || count($filtering['ids']) < 1)) {
            throw new InvalidArgumentException("过滤条件filtering.ids(计划ID) 长度限制 1-100");
        }
        if (isset($filtering['ad_create_start_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filtering['ad_create_start_date']) === 0) {
            throw new InvalidArgumentException("过滤条件filtering.ad_create_start_date(计划创建开始时间),格式：'yyyy-mm-dd'");
        }
        if (isset($filtering['ad_create_end_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filtering['ad_create_end_date']) === 0) {
            throw new InvalidArgumentException("过滤条件filtering.ad_create_end_date(计划创建结束时间),格式：'yyyy-mm-dd'");
        }
        if (isset($filtering['ad_modify_time']) && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}$/', $filtering['ad_modify_time']) === 0) {
            throw new InvalidArgumentException("过滤条件filtering.ad_modify_time(计划修改时间),格式：'yyyy-mm-dd HH'");
        }
        return $this->httpGet('/open_api/v1.0/qianchuan/ad/get/', [
            'advertiser_id' => $advertiserId,
            'request_aweme_info' => 1,
            'filtering' => json_encode($filtering),
            'page' => $page,
            'page_size' => $pageSize,
        ]);
    }

    //获取计划审核建议
    public function adRejectReason($advertiserId, array $adIds)
    {
        return $this->httpGet('/open_api/v1.0/qianchuan/ad/reject_reason/', [
            'advertiser_id' => $advertiserId,
            'ad_ids' => $adIds
        ]);
    }

    //更新创意状态
    public function creativeStatusUpdate($advertiserId, array $creativeIds, $optStatus)
    {
        return $this->httpJson('/open_api/v1.0/qianchuan/creative/status/update/', [
            'advertiser_id' => $advertiserId,
            'creative_ids' => $creativeIds,
            'opt_status' => $optStatus,
        ]);
    }

    //获取创意列表
    public function creativeGet($advertiserId, array $filtering, $page, $pageSize = 1000)
    {
        if (!in_array($pageSize, [10, 20, 50, 100, 500, 1000])) {
            throw new InvalidArgumentException("page_size(页面大小),允许值：10, 20, 50, 100, 500, 1000");
        }
        if (!isset($filtering['marketing_goal']) || !in_array($filtering['marketing_goal'], ['VIDEO_PROM_GOODS', 'LIVE_PROM_GOODS'])) {
            throw new InvalidArgumentException("过滤条件filtering.marketing_goal(营销目标),必填,允许值：VIDEO_PROM_GOODS, LIVE_PROM_GOODS");
        }
        if (isset($filtering['ad_ids']) && count($filtering['ad_ids']) > 100) {
            throw new InvalidArgumentException("过滤条件filtering.ad_ids(计划ID) 长度限制 1-100");
        }
        if (isset($filtering['creative_material_mode']) || !in_array($filtering['creative_material_mode'], ['CUSTOM_CREATIVE', 'PROGRAMMATIC_CREATIVE'])) {
            throw new InvalidArgumentException("过滤条件filtering.creative_material_mode(呈现方式),必填,允许值：CUSTOM_CREATIVE, PROGRAMMATIC_CREATIVE");
        }
        if (isset($filtering['creative_create_start_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filtering['creative_create_start_date']) === 0) {
            throw new InvalidArgumentException("过滤条件filtering.creative_create_start_date(创意创建开始时间),格式：'yyyy-mm-dd'");
        }
        if (isset($filtering['creative_create_end_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filtering['creative_create_end_date']) === 0) {
            throw new InvalidArgumentException("过滤条件filtering.creative_create_end_date(创意创建结束时间),格式：'yyyy-mm-dd'");
        }
        if (isset($filtering['creative_modify_time']) && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}$/', $filtering['creative_modify_time']) === 0) {
            throw new InvalidArgumentException("过滤条件filtering.creative_modify_time(创意修改时间),格式：'yyyy-mm-dd HH'");
        }
        return $this->httpGet('/open_api/v1.0/qianchuan/creative/get/', [
            'advertiser_id' => $advertiserId,
            'filtering' => $filtering,
            'page' => $page,
            'page_size' => $pageSize,
        ]);
    }

    //获取创意审核建议
    public function creativeRejectReason($advertiserId, array $creativeIds)
    {
        return $this->httpGet('/open_api/v1.0/qianchuan/creative/reject_reason/', [
            'advertiser_id' => $advertiserId,
            'creative_ids' => $creativeIds
        ]);
    }

    //获取广告账户数据
    public function reportAdvertiserGet($advertiserId, $startDate, $endDate, array $filtering, array $fields)
    {
        if (!isset($filtering['marketing_goal']) || !in_array($filtering['marketing_goal'], ['VIDEO_PROM_GOODS', 'LIVE_PROM_GOODS', 'ALL'])) {
            throw new InvalidArgumentException("过滤条件filtering.marketing_goal(营销目标),必填,允许值：VIDEO_PROM_GOODS, LIVE_PROM_GOODS, ALL");
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) === 0) {
            throw new InvalidArgumentException("过滤条件start_date(开始时间),格式：'yyyy-mm-dd'");
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate) === 0) {
            throw new InvalidArgumentException("过滤条件end_date(开始时间),格式：'yyyy-mm-dd'");
        }
        if (strtotime($endDate . ' 23:59:59') - strtotime($startDate . ' 00:00:00') > 180 * 24 * 3600) {
            throw new InvalidArgumentException("过滤条件start_date(开始时间)-end_date(开始时间),时间跨度不能超过180天");
        }
        return $this->httpGet('/open_api/v1.0/qianchuan/report/advertiser/get/', [
            'advertiser_id' => $advertiserId,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'filtering' => json_encode($filtering),
            'fields' => json_encode($fields),
        ]);
    }

    //获取广告计划数据
    public function reportAdGet($advertiserId, $startDate, $endDate, array $filtering, array $fields, $page, $pageSize = 500, $orderField = '', $orderType = 'ASC')
    {
        if ($pageSize < 1 || $pageSize > 500) {
            throw new InvalidArgumentException("page_size(页面大小),取值范围：1-500");
        }
        if (!in_array($orderType, ['ASC', 'DESC'])) {
            throw new InvalidArgumentException("order_type(排序方式),允许值：ASC 升序,DESC 降序");
        }
        if (!isset($filtering['marketing_goal']) || !in_array($filtering['marketing_goal'], ['VIDEO_PROM_GOODS', 'LIVE_PROM_GOODS', 'ALL'])) {
            throw new InvalidArgumentException("过滤条件filtering.marketing_goal(营销目标),必填,允许值：VIDEO_PROM_GOODS, LIVE_PROM_GOODS, ALL");
        }
        if (!isset($filtering['ad_ids']) || count($filtering['ad_ids']) > 100) {
            throw new InvalidArgumentException("过滤条件filtering.ad_ids(计划id),必填,最多支持100个");
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) === 0) {
            throw new InvalidArgumentException("过滤条件start_date(开始时间),格式：'yyyy-mm-dd'");
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate) === 0) {
            throw new InvalidArgumentException("过滤条件end_date(开始时间),格式：'yyyy-mm-dd'");
        }
        if (strtotime($endDate . ' 23:59:59') - strtotime($startDate . ' 00:00:00') > 180 * 24 * 3600) {
            throw new InvalidArgumentException("过滤条件start_date(开始时间)-end_date(开始时间),时间跨度不能超过180天");
        }
        $data = [
            'advertiser_id' => $advertiserId,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'filtering' => json_encode($filtering),
            'fields' => json_encode($fields),
            'page' => $page,
            'page_size' => $pageSize,
        ];
        if ($orderField) {
            $data['order_field'] = $orderField;
            $data['order_type'] = $orderType;
        }
        return $this->httpGet('/open_api/v1.0/qianchuan/report/ad/get/', $data);
    }

    //获取广告创意数据
    public function reportCreativeGet($advertiserId, $startDate, $endDate, array $filtering, array $fields, $page, $pageSize = 500, $orderField = '', $orderType = 'ASC')
    {
        if ($pageSize < 1 || $pageSize > 500) {
            throw new InvalidArgumentException("page_size(页面大小),取值范围：1-500");
        }
        if (!in_array($orderType, ['ASC', 'DESC'])) {
            throw new InvalidArgumentException("order_type(排序方式),允许值：ASC 升序,DESC 降序");
        }
        if (!isset($filtering['marketing_goal']) || !in_array($filtering['marketing_goal'], ['VIDEO_PROM_GOODS', 'LIVE_PROM_GOODS', 'ALL'])) {
            throw new InvalidArgumentException("过滤条件filtering.marketing_goal(营销目标),必填,允许值：VIDEO_PROM_GOODS, LIVE_PROM_GOODS, ALL");
        }
        if (!isset($filtering['creative_ids']) || count($filtering['creative_ids']) > 100) {
            throw new InvalidArgumentException("过滤条件filtering.creative_ids(创意id),必填,最多支持100个");
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) === 0) {
            throw new InvalidArgumentException("过滤条件start_date(开始时间),格式：'yyyy-mm-dd'");
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate) === 0) {
            throw new InvalidArgumentException("过滤条件end_date(开始时间),格式：'yyyy-mm-dd'");
        }
        if (strtotime($endDate . ' 23:59:59') - strtotime($startDate . ' 00:00:00') > 180 * 24 * 3600) {
            throw new InvalidArgumentException("过滤条件start_date(开始时间)-end_date(开始时间),时间跨度不能超过180天");
        }
        return $this->httpGet('/open_api/v1.0/qianchuan/report/ad/get/', [
            'advertiser_id' => $advertiserId,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'filtering' => json_encode($filtering),
            'order_field' => $orderField,
            'order_type' => $orderType,
            'page' => $page,
            'page_size' => $pageSize,
        ]);
    }
}