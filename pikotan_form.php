<?php

/**
 * Controller which manages page transition
 * 
 * @author nagayasu
 * @ported taiyoh
 *
 */
class Pikotan_SessionController
{
  /**
   * @var sfFlow_Continue
   */
  private $continue;
  
  /**
   * @var array
   */
  private $stateMap = array();
  
  /**
   * Constructor
   * 
   * @param sfFlow_Continue    $continue
   * @return void
   */
  public function __construct($ns, $initState)
  {
    $continue = Pikotan_SessionContinue::getInstance($ns);
    if ($continue->isInit()) {
      $continue->setState($initState);
    }
    
    $this->continue = $continue;
    $this->stateMap = array();
  }

  /**
   * Return template name
   * 
   * @return string
   */
  public function getTemplate()
  {
    $state = $this->continue->getState();
    if (!isset($this->stateMap[$state])) {
      throw new Exception(sprintf('The state [%s] is not registered.', $state));
    }
    
    return $this->stateMap[$state]->getTemplate();
  }
  
  /**
   * Execute controller
   * 
   * @return callable
   */
  public function execute()
  {
    $state = $this->continue->getState();
    if (!isset($this->stateMap[$state])) {
      throw new Exception(sprintf('The state [%s] is not registered.', $state));
    }

    foreach ($this->stateMap[$state] as $event => $callable) {
      if ($this->continue->happenEvent($event)) {
        if (is_callable($callable)) {
          call_user_func($callable);
        }

        break;
      }
    }
    $this->getContinue()->save();
    return $this->getContinue()->getState();
  }
  
  /**
   * Register event
   * 
   * @param $state    string
   * @param $event    string
   * @param $callback    mixed
   * @return void
   */
  public function addEvent($state, $event, $callback = null)
  {
    if (!isset($this->stateMap[$state])) {
      throw new Exception(sprintf('The state [%s] is not registered.', $state));
    }
    
    $this->stateMap[$state]->addEvent($event, $callback);
  }

  /**
   * Unregister event
   * 
   * @param $state    string
   * @param $event    string
   * @return void
   */
  public function removeEvent($state, $event)
  {
    if (!isset($this->stateMap[$state])) {
      throw new Exception(sprintf('The state [%s] is not registered.', $state));
    }
    
    $this->stateMap[$state]->removeEvent($event);
  }
  
  /**
   * Register state
   * 
   * @param $state
   * @param $template
   * @return void
   */
  public function addState($state, $template = null)
  {
    $this->stateMap[$state] = new Pikotan_SessionState($state, $template);
  }
  
  /**
   * Unregister state
   * 
   * @param $state
   * @return void
   */
  public function removeState($state)
  {
    unset($this->stateMap[$state]);
  }
  
  public function getContinue()
  {
    return $this->continue;
  }
}



/**
 * State management class to manage page transition. 
 * 
 * @author sei
 * @ported taiyoh
 */
class Pikotan_SessionContinue
{
  private $state  = null;
  private $flowId = null;
  private $ns     = null;
  private $init   = null;
  private $stash  = array();
  
  /**
   * Constructor
   * 
   * @param string    $ns
   * @param string    $flowId
   * @return void
   */
  private function __construct($ns, $flowId = null)
  {
    $this->ns = $ns;
    $this->flowId = ($flowId) ? $flowId : self::getRandomString();
    $this->stash = array();
  }

  public function setInit($init)
  {
    if (!is_bool($init)) {
      throw new Exception('$init should be boolean.');
    }
    
    $this->init = $init;
  }

  public function isInit()
  {
    return $this->init;
  }

  /**
   * Return the specific instance
   * 
   * @param $ns
   * @return sfFlow_Continue
   */
  public static function getInstance($ns)
  {
    $flowId   = get_param("flow_id");
    $sess_key = sprintf('%s/%s-flowContinue', $ns, $flowId);
    $instance = get_authparam($sess_key, null);

    if ($instance == null) {
      $instance = new self($ns, $flowId);
      add_header('Cache-Control', "");
      add_header('Pragma', "");
      $instance->setInit(true);
    } else {
      $instance->setInit(false);
    }
    
    return $instance;
  }

  /**
   * Return whether the given event was happen. 
   * 
   * @param $eventName
   * @return bool
   */
  public function happenEvent($eventName)
  {
    if (get_param($eventName, null) != null ||
    get_param($eventName . "_x", null) !== null ||
    get_param($eventName . "_y", null) !== null) {
      return true;
    }

    return false;
  }

  /**
   * Get state
   * 
   * @return string
   */
  public function getState()
  {
    return $this->state;
  }

  /**
   * Set state
   * 
   * @param $state
   * @return void
   */
  public function setState($state)
  {
    $this->state = $state;
    $this->save();
  }

  /**
   * Return flow_id
   * 
   * @return string
   */
  public function getFlowId()
  {
    return $this->flowId;
  }

  /**
   * Clear session, and remove namespace
   * 
   * @return void
   */
  public function remove()
  {
    $this->clearAttribute();
    $sess_key = sprintf('%s/%s', $this->ns, $this->flowId);
    foreach ($_SESSION as $k => $v) {
      if (preg_match('/^'.$sess_key.'/', $k)) {
        unset($_SESSION[$k]);
      }
    }
  }

  public function getAttribute($key)
  {
    return @$this->stash[$key];
  }

  public function setAttribute($key, $value)
  {
    $this->stash[$key] = $value;
    $this->save();
  }

  public function removeAttribute($key)
  {
    if (isset($this->stash[$key])) {
      unset($this->stash[$key]);
    }
  }

  public function clearAttribute()
  {
    $this->stash = array();
  }

  /**
   * Save the session. 
   * 
   * @return void
   */
  public function save()
  {
    $sess_key = sprintf('%s/%s-flowContinue', $this->ns, $this->flowId);
    set_authparam($sess_key, $this);
  }

  /**
   * Generate a random character string. 
   * 
   * @param int    $nLengthRequired
   * @return string
   */
  private static function getRandomString($nLengthRequired = 8){
    $sCharList = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_";
    mt_srand();

    $sRes = "";
    for ($i=0; $i<$nLengthRequired; $i++) {
      $sRes .= $sCharList{mt_rand(0, strlen($sCharList) - 1)};
    }

    return $sRes;
  }
}

/**
 * Event managed object maintained by controller
 * 
 * @author nagayasu
 * @ported taiyoh
 *
 */
class Pikotan_SessionState
{
  /**
   * @var string    State name
   */
  private $state;
  
  /**
   * @var string    Template name corresponding to state
   */
  private $template;

  /**
   * @var ArrayObject
   */
  private $eventMap;
  
  /**
   * @var ArrayIterator
   */
  private $eventMapIterator;
  
  /**
   * Constructor
   * 
   * @param $state
   * @param $template
   * @return void
   */
  public function __construct($state, $template = null)
  {
    // When omitting, set the same name.
    if (!$template) {
      $template = $state;
    }

    $this->state    = $state;
    $this->template = $template;
    $this->eventMap = new ArrayObject(array());
    $this->eventMapIterator = $this->eventMap->getIterator();
  }

  /**
   * getter $template
   * 
   * @return string
   */
  public function getTemplate()
  {
    return $this->template;
  }

  /**
   * getter $state
   * 
   * @return string
   */
  public function getState()
  {
    return $this->state;
  }

  /**
   * Register event
   * 
   * @param $event
   * @param $callable
   * @return void
   */
  public function addEvent($event, $callable = null)
  {
    $this->eventMap[$event] = $callable;
  }
  
  /**
   * Unregister event
   * 
   * @param $event
   * @return void
   */
  public function removeEvent($event)
  {
    unset($this->eventMap[$event]);
  }
  
  public function current()
  {
    return $this->eventMapIterator->current();
  }
  public function key()
  {
    return $this->eventMapIterator->key();
  }
  public function next()
  {
    return $this->eventMapIterator->next();
  }
  public function rewind()
  {
    return $this->eventMapIterator->rewind();
  }
  public function seek($pos)
  {
    return $this->eventMapIterator->seek(pos);
  }
  public function valid()
  {
    return $this->eventMapIterator->valid();
  }
}