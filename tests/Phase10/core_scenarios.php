<?php

declare(strict_types=1);

use CoffeePOS\Application\Contracts\CartReconstructorInterface;
use CoffeePOS\Application\Contracts\LockProviderInterface;
use CoffeePOS\Application\Contracts\OperationalOrderGatewayInterface;
use CoffeePOS\Application\Contracts\OrderHistoryGatewayInterface;
use CoffeePOS\Application\Error\Phase01ErrorCodes;
use CoffeePOS\Application\Error\Phase01Exception;
use CoffeePOS\Application\Operations\OperationalOrderService;
use CoffeePOS\Application\OrderHistory\OrderHistoryService;
use CoffeePOS\Application\Projection\CartView;
use CoffeePOS\Domain\Cart\Cart;

require dirname(__DIR__, 2) . '/vendor/autoload.php';
if (! function_exists('sanitize_key')) { function sanitize_key($v){ return strtolower(preg_replace('/[^a-z0-9_\-]/','',(string)$v)); } }
if (! function_exists('sanitize_text_field')) { function sanitize_text_field($v){ return trim(strip_tags((string)$v)); } }
if (! function_exists('wp_json_encode')) { function wp_json_encode($v){ return json_encode($v); } }
if (! function_exists('wp_timezone')) { function wp_timezone(){ return new DateTimeZone('UTC'); } }
if (! function_exists('wc_get_price_decimals')) { function wc_get_price_decimals(){ return 2; } }
if (! function_exists('get_woocommerce_currency')) { function get_woocommerce_currency(){ return 'VND'; } }
if (! function_exists('wc_get_order_statuses')) { function wc_get_order_statuses(){ return ['wc-processing'=>'Processing','wc-completed'=>'Completed','wc-cancelled'=>'Cancelled','wc-refunded'=>'Refunded']; } }

$failures=[]; $assert=static function(bool $ok,string $message)use(&$failures):void{if(!$ok)$failures[]=$message;};
$gateway=new class implements OrderHistoryGatewayInterface {
    public array $reorders=[]; public int $refunds=0;
    public array $order=['id'=>12,'number'=>'12','status'=>'completed','actions'=>['can_refund'=>true],'kds'=>['state'=>'completed','revision'=>3]];
    public function list(array $criteria):array{return ['items'=>[$this->order],'page'=>$criteria['page'],'pages'=>1,'total'=>1,'criteria'=>$criteria];}
    public function find(int $id):?array{return $id===12?$this->order:null;}
    public function refund(int $id,string $amount,string $reason,string $operation,string $fingerprint,int $user):array{$this->refunds++;$this->order['refund_amount']=$amount;$this->order['refund_operation']=$operation;return $this->order;}
    public function reorderItems(int $id):array{return [['product_id'=>1,'variation_id'=>0,'quantity'=>2,'modifiers'=>[],'quick_notes'=>[],'custom_note'=>'']];}
    public function findReorderOperation(int $id,string $operation):?array{return $this->reorders[$operation]??null;}
    public function recordReorderOperation(int $id,string $operation,string $fingerprint,string $session):void{$this->reorders[$operation]=['id'=>$operation,'fingerprint'=>$fingerprint,'pos_session_id'=>$session];}
};
$carts=new class implements CartReconstructorInterface {
    public int $creates=0; public array $sessions=[];
    public function getSession(string $id):CartView{if(!isset($this->sessions[$id]))throw new RuntimeException('missing');return $this->sessions[$id];}
    public function reconstruct(string $currency,array $items):CartView{$this->creates++;$id='phase10-session-'.$this->creates.'-abcdef';$view=CartView::fromDomain(Cart::createSession($currency,$id,'2026-08-23T10:00:00Z'));$this->sessions[$id]=$view;return $view;}
};
$operationalGateway=new class implements OperationalOrderGatewayInterface {
    public array $order=['id'=>12,'number'=>'12','eligible'=>true,'kds'=>['state'=>'new','revision'=>0],'_operations'=>[]];
    public function listActive(int $limit):array{return[$this->order];} public function find(int $id):?array{return$id===12?$this->order:null;}
    public function saveTransition(int $id,int $revision,string $state,array $changes):array{$this->order['kds']['state']=$changes['state'];$this->order['kds']['revision']=$changes['revision'];$this->order['_operations']=$changes['operations'];return$this->order;}
};
$locks=new class implements LockProviderInterface { public array $keys=[]; public function synchronized(string $key,callable $callback){$this->keys[]=$key;return$callback();} };
$service=new OrderHistoryService($gateway,$carts,new OperationalOrderService($operationalGateway),$locks);

$list=$service->list(['page'=>1,'per_page'=>20,'status'=>'completed','order_type'=>'takeaway','date_from'=>'2026-08-01','date_to'=>'2026-08-31','search'=>'12']);
$assert($list['total']===1&&$list['criteria']['date_from_ts']>0,'TC-01 list/filter normalization failed.');
try{$service->list(['status'=>'invented']);$assert(false,'TC-02 invalid status accepted.');}catch(Phase01Exception $e){$assert($e->errorCode()===Phase01ErrorCodes::INVALID_ORDER_FILTER,'TC-02 wrong filter error.');}
$assert($service->detail(12)['number']==='12','TC-03 detail failed.');
try{$service->detail(99);$assert(false,'TC-04 missing order accepted.');}catch(Phase01Exception $e){$assert($e->errorCode()===Phase01ErrorCodes::ORDER_NOT_FOUND,'TC-04 wrong missing error.');}
$refunded=$service->refund(12,'50','Customer request','refund-operation-0001',7);
$assert($refunded['refund_amount']==='50.00'&&$gateway->refunds===1&&strpos($locks->keys[0],'refund-order-')===0,'TC-05 refund normalization/lock failed.');
$first=$service->reorder(12,'reorder-operation-0001');$second=$service->reorder(12,'reorder-operation-0001');
$assert($first['replayed']===false&&$second['replayed']===true&&$carts->creates===1,'TC-06 reorder idempotency failed.');
$cancelled=$service->cancel(12,'new',0,'cancel-operation-0001',7,'Customer request');
$assert($cancelled['kds']['state']==='cancelled','TC-07 Phase 07 cancellation reuse failed.');

$root=dirname(__DIR__,2);$source=static function(string $p)use($root):string{return(string)file_get_contents($root.'/'.$p);};
$gatewaySource=$source('includes/Integration/WooCommerce/WooCommerceOrderHistoryGateway.php');
$assert(strpos($gatewaySource,'wc_get_orders')!==false&&strpos($gatewaySource,'created_via')!==false,'TC-08 HPOS/CoffeePOS list boundary missing.');
$assert(strpos($gatewaySource,'wc_create_refund')!==false&&strpos($gatewaySource,"'refund_payment' => false")!==false,'TC-09 WooCommerce refund authority missing.');
$assert(strpos($source('includes/Application/Cart/CartSessionService.php'),'function reconstruct')!==false,'TC-10 cart reconstruction missing.');
$ui=$source('templates/order-history/content.php').$source('assets/js/screens/order-history.js');
$assert(strpos($ui,'data-template="history-order-card"')!==false&&strpos($ui,'innerHTML')===false,'TC-11 PHP template ownership failed.');
$assert(strpos($source('includes/Integration/WooCommerce/WooCommerceOrderGateway.php'),"'_coffeepos_shift_id'")!==false,'TC-12 shift association regression.');

if($failures){foreach($failures as$f)echo'[FAIL] '.$f.PHP_EOL;exit(1);}echo"Phase 10 core scenarios passed.\n";
