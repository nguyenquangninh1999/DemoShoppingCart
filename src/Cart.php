<?php

namespace Ninh\ShoppingCart;

use Closure;
use Illuminate\Support\Collection;
use Illuminate\Session\SessionManager;
use Illuminate\Database\DatabaseManager;
use Illuminate\Contracts\Events\Dispatcher;
use Gloudemans\Shoppingcart\Contracts\Buyable;
use Gloudemans\Shoppingcart\Exceptions\UnknownModelException;
use Gloudemans\Shoppingcart\Exceptions\InvalidRowIDException;
use Gloudemans\Shoppingcart\Exceptions\CartAlreadyStoredException;
use Illuminate\Support\Facades\DB;

class Cart
{
    const DEFAULT_INSTANCE = 'default';

    private $session;

    private $events;

    private $instance;

    public function __construct(SessionManager $session)
    {
        $this->session = $session;

        $this->instance(self::DEFAULT_INSTANCE);
    }

    public function instance($instance = null)
    {
        $instance = $instance ?: self::DEFAULT_INSTANCE;
        return $instance;
    }

    public function add($id, $name = null, $qty = null, $price = null, array $options = [])
    {

        // lấy ra thông tin item nhập vào
        $cartItem = $this->createCartItem($id, $name, $qty, $price, $options);

        $content = $this->getContent();
        // kiểm tra rowID có trong session hay chưa, nếu có rồi thì cộng thêm qty (số lượng)
        if ($content->has($cartItem->rowId)) {
            $cartItem->qty += $content->get($cartItem->rowId)->qty;
        }
        //lưu thông tin item vào content
        $content->put($cartItem->rowId, $cartItem);
        //lưu thông tin item vào session
        $this->session->put($this->instance, $content);

        return $cartItem;
    }

    protected function getContent()
    {
        // kiểm tra xem tồn tại session chưa nếu chưa thì item rỗng, có rồi thì lấy gitri của item
        $content = $this->session->has($this->instance)
            ? $this->session->get($this->instance)
            : new Collection;

        return $content;
    }

    private function createCartItem($id, $name, $qty, $price, array $options)
    {
        // kiểm tra id có phải mảng hay không
        if (is_array($id)) {
            //lấy ra mảng item
            $cartItem = CartItem::fromArray($id);
            //thêm vào mảng 1 trường qty
            $cartItem->setQuantity($id['qty']);
        } else {
            //lấy ra mảng item
            $cartItem = CartItem::fromAttributes($id, $name, $price, $options);
            //thêm vào mảng 1 trường qty
            $cartItem->setQuantity($qty);
        }

        return $cartItem;
    }

    public function content()
    {
        // kiểm tra session có dữ liệu k
        if (is_null($this->session->get($this->instance))) {
            return new Collection([]);
        }

        return $this->session->get($this->instance);
    }

    public function update($rowId, $qty)
    {
        //lấy ra giá trị session có rowID
        $cartItem = $this->get($rowId);
        //lấy ra dữ liệu session
        $cartItem->qty = $qty;
        //lấy ra dữ liệu session
        $content = $this->getContent();
        //kiểm tra qty <=0
        if ($cartItem->qty <= 0) {
            //xóa session rowID
            $this->remove($cartItem->rowId);
            return;
        } else {
            $content->put($cartItem->rowId, $cartItem);
        }
        //lưu conten vào session
        $this->session->put($this->instance, $content);

        return $cartItem;
    }

    public function remove($rowId)
    {
        //lấy thông tin session rowID
        $cartItem = $this->get($rowId);

        $content = $this->getContent();
        //xóa session rowID
        $content->pull($cartItem->rowId);
        //lưu lại session sau khi xóa
        $this->session->put($this->instance, $content);
    }

    public function get($rowId)
    {
        $content = $this->getContent();
        //lấy dữ liệu theo rowID
        return $content->get($rowId);
    }

    public function store($identifier)
    {
        //lấy nội dung session
        $content = $this->getContent();
        //lưu vào csdl
        DB::table(config('cart.database.table'))->insert([
            'identifier' => $identifier,
            'instance' => $this->instance ?? 'default',
            'content' => serialize($content)
        ]);
    }

    public function total($decimals = null, $decimalPoint = null, $thousandSeperator = null)
    {
        //lấy thông tin session
        $content = $this->getContent();

        $total = 0;
        foreach ($content as $value) {
            //tính tổng tất cả các mặt hàng
            $total += $value->qty * $value->price;
        }
        return $total ?? 0;
    }

    public function destroy()
    {
        //xóa tất cả session
        $this->session->remove($this->instance);
    }
}
