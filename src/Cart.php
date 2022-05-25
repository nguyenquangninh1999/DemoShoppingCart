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

        $this->instance = self::DEFAULT_INSTANCE;
    }

    public function add($id, $name = null, $qty = null, $price = null, array $options = [])
    {

        $cartItem = $this->createCartItem($id, $name, $qty, $price, $options);

        $content = $this->getContent();

        if ($content->has($cartItem->rowId)) {
            $cartItem->qty += $content->get($cartItem->rowId)->qty;
        }

        $content->put($cartItem->rowId, $cartItem);

        $this->session->put($this->instance, $content);

        return $cartItem;
    }

    protected function getContent()
    {
        $content = $this->session->has($this->instance)
            ? $this->session->get($this->instance)
            : new Collection;

        return $content;
    }

    private function createCartItem($id, $name, $qty, $price, array $options)
    {
        if (is_array($id)) {
            $cartItem = CartItem::fromArray($id);
            $cartItem->setQuantity($id['qty']);
        } else {
            $cartItem = CartItem::fromAttributes($id, $name, $price, $options);
            $cartItem->setQuantity($qty);
        }

        return $cartItem;
    }

    public function content()
    {
        if (is_null($this->session->get($this->instance))) {
            return new Collection([]);
        }

        return $this->session->get($this->instance);
    }

    public function update($rowId, $qty)
    {
        $cartItem = $this->get($rowId);

        $cartItem->qty = $qty;

        $content = $this->getContent();

        if ($cartItem->qty <= 0) {
            $this->remove($cartItem->rowId);
            return;
        } else {
            $content->put($cartItem->rowId, $cartItem);
        }

        $this->session->put($this->instance, $content);

        return $cartItem;
    }

    public function remove($rowId)
    {
        $cartItem = $this->get($rowId);

        $content = $this->getContent();

        $content->pull($cartItem->rowId);

        $this->session->put($this->instance, $content);
    }

    public function get($rowId)
    {
        $content = $this->getContent();

        return $content->get($rowId);
    }

    public function store($identifier)
    {
        $content = $this->getContent();

        DB::table(config('cart.database.table'))->insert([
            'identifier' => $identifier,
            'instance' => $this->instance ?? 'default',
            'content' => serialize($content)
        ]);
    }

    public function total($decimals = null, $decimalPoint = null, $thousandSeperator = null)
    {
        $content = $this->getContent();

        $total = 0;
        foreach ($content as $value) {
            $total += $value->qty * $value->price;
        }
        return $total ?? 0;
    }

    public function destroy()
    {
        $this->session->remove($this->instance);
    }
}
