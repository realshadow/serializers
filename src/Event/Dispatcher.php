<?php
	namespace Serializers\Event;

	use InvalidArgumentException;
	use Closure;
	use ReflectionFunction;

	/**
	 * Event dispatcher
	 *
	 * @package Serializers\Event
	 * @author Lukas Homza <lukashomz@gmail.com>
	 * @version 1.0
	 */
	class Dispatcher {
		/** @var array $events - list of registered events */
		protected $events = array();
		/** @var \ReflectionFunction[] $cache - list of registered events */
		protected $cache = array();

		/**
		 * Method for registering event callbacks
		 *
		 * Callback must be a Closure or instance of callable
		 *
		 * @param string $event - event name
		 * @param \Closure|callable $callback - event callback
		 *
		 * @return $this
		 *
		 * @since 1.0
		 */
		public function subscribe($event, $callback) {
			if(!$callback instanceof Closure && !is_callable($callback)) {
				throw new InvalidArgumentException('Provided event callback must be callable or a valid instance of Closure.');
			}

			$this->events[$event] = $callback;

			return $this;
		}

		/**
		 * Method for removing events
		 *
		 * @param string $event - event name
		 *
		 * @return $this
		 *
		 * @since 1.0
		 */
		public function unsubscribe($event) {
			if($this->listensTo($event)) {
				unset($this->events[$event]);
			}

			return $this;
		}

		/**
		 * Check if event is registered
		 *
		 * @param string $event - event name
		 *
		 * @return bool
		 *
		 * @since 1.0
		 */
		public function listensTo($event) {
			return isset($this->events[$event]);
		}

		/**
		 * Returns registered callback for provided event
		 *
		 * @param string $event - event name
		 *
		 * @return mixed
		 *
		 * @since 1.0
		 */
		public function getCallback($event) {
			return $this->events[$event];
		}

		/**
		 * Method will execute callback associated with provided event
		 *
		 * @param string $event - event name
		 * @param array $params - params passed to event callback
		 *
		 * @return mixed
		 *
		 * @since 1.0
		 */
		public function trigger($event, array $params) {
			$callback = $this->events[$event];

			if($callback instanceof Closure) {
				if(empty($this->cache[$event])) {
					$reflection = new ReflectionFunction($callback);

					$this->cache[$event] = $reflection;
				}

				return $this->cache[$event]->invokeArgs($params);
			}

			return call_user_func_array($callback, $params);
		}
	}