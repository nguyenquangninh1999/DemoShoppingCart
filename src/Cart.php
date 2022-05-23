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

class Cart
{
    const DEFAULT_INSTANCE = 'default';

    private $session;

    private $events;

    private $instance;

    public function __construct(SessionManager $session, Dispatcher $events)
    {
        $this->session = $session;
        $this->events  = $events;

        $this->instance(self::DEFAULT_INSTANCE);
    }

    public function instance($instance = null)
    {
        $instance = $instance ?: self::DEFAULT_INSTANCE;

        $this->instance = sprintf('%s.%s', 'cart', $instance);

        return $this;
    }

    public function add($id, $name = null, $qty = null, $price = null, array $options = [])
    {
//        if ($this->isMulti($id)) {
//            return array_map(function ($item) {
//                return $this->add($item);
//            }, $id);
//        }

        $cartItem = $this->createCartItem($id, $name, $qty, $price, $options);

        $content = $this->getContent();

        if ($content->has($cartItem->rowId)) {
            $cartItem->qty += $content->get($cartItem->rowId)->qty;
        }

        $content->put($cartItem->rowId, $cartItem);

        $this->events->fire('cart.added', $cartItem);

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

//        $cartItem->setTaxRate(config('cart.tax'));

        return $cartItem;
    }

    public function content()
    {
        if (is_null($this->session->get($this->instance))) {
            return new Collection([]);
        }

        return $this->session->get($this->instance);
    }
}
