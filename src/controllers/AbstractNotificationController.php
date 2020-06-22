<?php


namespace invoice\payment\controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller as BaseController;

abstract class AbstractNotificationController extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    abstract function onPay($orderId, $amount);
    abstract function onFail($orderId);
    abstract function onRefund($orderId);

    /**
     * @return JsonResponse
     */
    public function notify() {
        $postData = file_get_contents('php://input');
        $notification = json_decode($postData, true);

        $key = config("invoice.api_key");

        if($notification == null or empty($notification)) return new JsonResponse(["result" => "error"]);
        $type = $notification["notification_type"];
        $id = $notification["order"]["id"];

        if(!isset($notification['status'])) return new JsonResponse(["result" => "error"]);
        if($notification['signature'] != $this->getSignature($notification['id'], $notification["status"], $key))
            return new JsonResponse(["result" => "wrong signature"]);

        if($type == "pay") {
            switch ($notification['status']) {
                case "successful":
                    $this->onPay($id, $notification['order']['amount']);
                    break;
                case "failed":
                    $this->onFail($id);
                    break;
            }
        }

        if($type == "refund") {
            $this->onRefund($id);
        }

        return new JsonResponse(["result" => "ok"]);
    }

    private function getSignature($id, $status, $key) {
        return md5($id.$status.$key);
    }
}
