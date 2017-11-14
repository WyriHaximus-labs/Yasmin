<?php
/**
 * Yasmin
 * Copyright 2017 Charlotte Dunois, All Rights Reserved
 *
 * Website: https://charuru.moe
 * License: https://github.com/CharlotteDunois/Yasmin/blob/master/LICENSE
*/

namespace CharlotteDunois\Yasmin;

/**
 * The client. What else do you expect this to say?
 *
 * @property \CharlotteDunois\Yasmin\Models\ChannelStorage   $channels          It holds all cached channels, mapped by ID.
 * @property \CharlotteDunois\Yasmin\Models\EmojiStorage     $emojis            It holds all emojis, mapped by ID (custom emojis) and/or name (unicode emojis).
 * @property \CharlotteDunois\Yasmin\Models\GuildStorage     $guilds            It holds all guilds, mapped by ID.
 * @property \CharlotteDunois\Yasmin\Models\PresenceStorage  $presences         It holds all cached presences (latest ones), mapped by user ID.
 * @property \CharlotteDunois\Yasmin\Models\UserStorage      $users             It holds all cached users, mapped by ID.
 * @property \CharlotteDunois\Yasmin\Utils\Collection        $voiceConnections  It holds all open voice connections, mapped by guild ID.
 * @property \CharlotteDunois\Yasmin\Models\ClientUser|null  $user              Get the Client User instance.
 *
 * @see \CharlotteDunois\Yasmin\ClientEvents
 * @todo Implementation
 */
class Client extends \CharlotteDunois\Events\EventEmitter2 {
    /**
     * It holds all cached channels, mapped by ID.
     * @var \CharlotteDunois\Yasmin\Models\ChannelStorage<\CharlotteDunois\Yasmin\Interfaces\ChannelInterface>
     * @internal
     */
    protected $channels;
    
    /**
     * It holds all emojis, mapped by ID (custom emojis) and/or name (unicode emojis).
     * @var \CharlotteDunois\Yasmin\Models\EmojiStorage<\CharlotteDunois\Yasmin\Models\Emoji>
     * @internal
     */
    protected $emojis;
    
    /**
     * It holds all guilds, mapped by ID.
     * @var \CharlotteDunois\Yasmin\Models\GuildStorage<\CharlotteDunois\Yasmin\Models\Guild>
     * @internal
     */
    protected $guilds;
    
    /**
     * It holds all cached presences (latest ones), mapped by user ID.
     * @var \CharlotteDunois\Yasmin\Models\PresenceStorage<\CharlotteDunois\Yasmin\Models\PresenceStorage>
     * @internal
     */
    protected $presences;
    
    /**
     * It holds all cached users, mapped by ID.
     * @var \CharlotteDunois\Yasmin\Models\UserStorage<\CharlotteDunois\Yasmin\Models\User>
     * @internal
     */
    protected $users;
    
    /**
     * It holds all open voice connections, mapped by guild ID.
     * @var \CharlotteDunois\Yasmin\Utils\Collection<\CharlotteDunois\Yasmin\Voice\VoiceConnection>
     * @internal
     */
    protected $voiceConnections;
    
    /**
     * The last 3 websocket pings (in ms).
     * @var int[]
     */
    public $pings = array();
    
    /**
     * The UNIX timestamp of the last emitted ready var $(or null if none yet).
     * @var int|null
     */
    public $readyTimestamp = null;
    
    /**
     * The token.
     * @var string|null
     */
    public $token;
    
    /**
     * The event loop.
     * @var \React\EventLoop\LoopInterface
     * @internal
     */
    protected $loop;
    
    /**
     * Client Options.
     * @var array
     * @internal
     */
    protected $options = array();
    
    /**
     * The Client User.
     * @var \CharlotteDunois\Yasmin\Models\ClientUser|null
     * @internal
     */
    protected $user;
    
    /**
     * @var \CharlotteDunois\Yasmin\HTTP\APIManager
     * @internal
     */
    protected $api;
    
    /**
     * @var \CharlotteDunois\Yasmin\WebSocket\WSManager
     * @internal
     */
    protected $ws;
    
    /**
     * Gateway address.
     * @var string
     * @internal
     */
    protected $gateway;
    
    /**
     * Timers which automatically get cancelled on destroy and only get run when we have a WS connection.
     * @var \React\EventLoop\Timer\Timer[]
     * @internal
     */
    protected $timers = array();
    
    /**
     * Loaded Utils with a loop instance.
     * @var array
     * @internal
     */
    protected $utils = array();
    
    /**
     * What do you expect this to do? It makes a new Client instance. Available client options are as following (all are optional):
     *
     *  array( <br />
     *      'disableClones' => string[], (disables cloning of objects (for perfomance), affects update events) <br />
     *      'disableEveryone' => bool, (disables the everyone and here mentions and replaces them with plaintext) <br />
     *      'fetchAllMembers' => bool, (fetches all guild members, this should be avoided - necessary members get automatically fetched) <br />
     *      'messageCacheLifetime' => int, (invalidates messages in the store older than the specified duration) <br />
     *      'shardID' => int, (shard ID, important for sharding) <br />
     *      'shardCount' => int, (shard count, important for sharding) <br />
     *      'http.restTimeOffset' => int|float, (specifies how many seconds should be waited after one REST request before the next REST request should be done) <br />
     *      'ws.compression' => bool|string, (disables transport compression of the WebSocket connection, or enables a specific one, defaults to zlib-stream) <br />
     *      'ws.disabledEvents' => string[], (disables specific websocket events (e.g. TYPING_START)) <br />
     *      'ws.largeThreshold' => int, (50-250, threshold after which guilds gets counted as large, defaults to 250) <br />
     *      'ws.presence' => array (the presence to send on WS connect) <br />
     *  )
     *
     * @param array                            $options  Any client options.
     * @param \React\EventLoop\LoopInterface   $loop     You can pass an event loop to the class, or it will automatically create one (you still need to make it run yourself).
     */
    function __construct(array $options = array(), \React\EventLoop\LoopInterface $loop = null) {
        if(!empty($options)) {
            $this->validateClientOptions($options);
            $this->options = \array_merge($this->options, $options);
        }
        
        if(!$loop) {
            $loop = \React\EventLoop\Factory::create();
        }
        
        $this->loop = $loop;
        
        $this->api = new \CharlotteDunois\Yasmin\HTTP\APIManager($this);
        $this->ws = new \CharlotteDunois\Yasmin\WebSocket\WSManager($this);
        
        $this->channels = new \CharlotteDunois\Yasmin\Models\ChannelStorage($this);
        $this->emojis = new \CharlotteDunois\Yasmin\Models\EmojiStorage($this);
        $this->guilds = new \CharlotteDunois\Yasmin\Models\GuildStorage($this);
        $this->presences = new \CharlotteDunois\Yasmin\Models\PresenceStorage($this);
        $this->users = new \CharlotteDunois\Yasmin\Models\UserStorage($this);
        $this->voiceConnections = new \CharlotteDunois\Yasmin\Utils\Collection();
        
        $this->registerUtils();
    }
    
    /**
     * @throws \Exception
     * @internal
     */
    function __get($name) {
        $props = array('channels', 'emojis', 'guilds', 'presences', 'users', 'voiceConnections', 'user');
        
        if(\in_array($name, $props)) {
            return $this->$name;
        }
        
        throw new \Exception('Unknown property \CharlotteDunois\Yasmin\Client::'.$name);
    }
    
    /**
     * You don't need to know.
     * @return \CharlotteDunois\Yasmin\HTTP\APIManager
     * @internal
     */
    function apimanager() {
        return $this->api;
    }
    
    /**
     * You don't need to know.
     * @return \CharlotteDunois\Yasmin\WebSocket\WSManager
     * @internal
     */
    function wsmanager() {
        return $this->ws;
    }
    
    /**
     * Get the React Event Loop that is stored in this class.
     * @return \React\EventLoop\LoopInterface
     */
    function getLoop() {
        return $this->loop;
    }
    
    /**
     * Get a specific option, or the default value.
     * @param string  $name
     * @param mixed   $default
     * @return mixed
     */
    function getOption($name, $default = null) {
        if(isset($this->options[$name])) {
            return $this->options[$name];
        }
        
        return $default;
    }
    
    /**
     * Gets the average ping. Or NAN.
     * @return int|double
     */
    function getPing() {
        $cpings = \count($this->pings);
        if($cpings === 0) {
            return \NAN;
        }
        
        return ((int) \ceil(\array_sum($this->pings) / $cpings));
    }
    
    /**
     * Returns the WS status.
     * @return int
     */
    function getWSstatus() {
        return $this->ws->status;
    }
    
    /**
     * Login into Discord. Opens a WebSocket Gateway connection. Resolves once a WebSocket connection has been successfully established (does not mean the client is ready).
     * @param string $token  Your token.
     * @param bool   $force  Forces the client to get the gateway address from Discord.
     * @return \React\Promise\Promise
     */
    function login(string $token, bool $force = false) {
        $this->token = \trim($token);
        
        return (new \React\Promise\Promise(function (callable $resolve, callable $reject) use ($force) {
            if($this->gateway && !$force) {
                $gateway = \React\Promise\resolve($this->gateway);
            } else {
                if($this->gateway) {
                    $gateway = $this->api->getGateway();
                } else {
                    $gateway = $this->api->getGatewaySync();
                }
                
                $gateway = $gateway->then(function ($response) {
                    return $response['url'];
                });
            }
            
            $gateway->then(function ($url) use ($resolve, $reject) {
                $this->gateway = $url;
                
                $this->ws->connect($url, \CharlotteDunois\Yasmin\Constants::WS)->then(function () use ($resolve) {
                    $resolve();
                }, function ($error) use ($reject) {
                    $this->api->destroy();
                    $this->ws->destroy();
                    
                    foreach($this->timers as $timer) {
                        $this->cancelTimer($timer['timer']);
                    }
                    
                    $this->destroyUtils();
                    $reject($error);
                })->done();
            }, $reject)->done();
        }));
    }
    
    /**
     * Cleanly logs out of Discord.
     * @param  bool  $destroyUtils  Stop timers of utils which have an instance of the event loop.
     * @return \React\Promise\Promise
     */
    function destroy(bool $destroyUtils = true) {
        return (new \React\Promise\Promise(function (callable $resolve) use ($destroyUtils) {
            $this->api->destroy();
            $this->ws->destroy();
            
            foreach($this->timers as $timer) {
                $this->cancelTimer($timer['timer']);
            }
            
            if($destroyUtils) {
                $this->destroyUtils();
            }
            
            $resolve();
        }));
    }
    
    /**
     * Obtains the OAuth Application of the bot from Discord.
     * @return \React\Promise\Promise
     * @see \
     */
    function fetchApplication() {
        return (new \React\Promise\Promise(function (callable $resolve, callable $reject) {
            $this->api->endpoints->getCurrentApplication()->then(function ($data) use ($resolve) {
                $app = new \CharlotteDunois\Yasmin\Models\OAuthApplication($this, $data);
                $resolve($app);
            }, $reject)->done(null, array($this, 'handlePromiseRejection'));
        }));
    }
    
    /**
     * Obtains an invite from Discord. Resolves with an instance of Invite.
     * @param string  $invite  The invite code or an invite URL.
     * @return \React\Promise\Promise
     * @see \CharlotteDunois\Yasmin\Models\Invite
     */
    function fetchInvite(string $invite) {
        return (new \React\Promise\Promise(function (callable $resolve, callable $reject) use ($invite) {
            \preg_match('/discord(?:app\.com\/invite|\.gg)\/([\w-]{2,255})/i', $invite, $matches);
            if(!empty($matches[1])) {
                $invite = $matches[1];
            }
            
            $this->api->endpoints->invite->getInvite($invite)->then(function ($data) use ($resolve) {
                $invite = new \CharlotteDunois\Yasmin\Models\Invite($this, $data);
                $resolve($invite);
            }, $reject)->done(null, array($this, 'handlePromiseRejection'));
        }));
    }
    
    /**
     * Fetches an User from the API. Resolves with an User.
     * @param string  $userid  The User ID to fetch.
     * @return \React\Promise\Promise
     * @see \CharlotteDunois\Yasmin\Models\User
     */
    function fetchUser(string $userid) {
        return (new \React\Promise\Promise(function (callable $resolve, $reject) use  ($userid) {
            if($this->users->has($userid)) {
                return $resolve($this->users->get($userid));
            }
            
            $this->api->endpoints->user->getUser($userid)->then(function ($user) use ($resolve) {
                $user = $this->users->factory($user);
                $resolve($user);
            }, $reject);
        }));
    }
    
    /**
     * Obtains the available voice regions from Discord. Resolves with a Collection of Voice Region instances, mapped by their ID.
     * @return \React\Promise\Promise
     * @see \CharlotteDunois\Yasmin\Models\VoiceRegion
     */
    function fetchVoiceRegions() {
        return (new \React\Promise\Promise(function (callable $resolve, callable $reject) {
            $this->api->endpoints->voice->listVoiceRegions()->then(function ($data) use ($resolve) {
                $collect = new \CharlotteDunois\Yasmin\Utils\Collection();
                
                foreach($data as $region) {
                    $voice = new \CharlotteDunois\Yasmin\Models\VoiceRegion($this, $region);
                    $collect->set($voice->id, $voice);
                }
                
                $resolve($collect);
            }, $reject)->done(null, array($this, 'handlePromiseRejection'));
        }));
    }
    
    /**
     * Fetches a webhook from Discord. Resolves with an instance of Webhook.
     * @param string       $id
     * @param string|null  $token
     * @return \React\Promise\Promise
     * @see \CharlotteDunois\Yasmin\Models\Webhook
     */
    function fetchWebhook(string $id, string $token = null) {
        return (new \React\Promise\Promise(function (callable $resolve, callable $reject) use ($id, $token) {
            $method = (!empty($token) ? 'getWebhookToken' : 'getWebhook');
            
            $this->api->endpoints->webhook->$method($id, $token)->then(function ($data) use ($resolve) {
                $hook = new \CharlotteDunois\Yasmin\Models\Webhook($this, $data);
                $resolve($hook);
            }, $reject)->done(null, array($this, 'handlePromiseRejection'));
        }));
    }
    
    /**
     * Generates a link that can be used to invite the bot to a guild. Resolves with a string.
     * @param string|int  $permissions
     * @return \React\Promise\Promise
     * @throws \InvalidArgumentException
     */
    function generateOAuthInvite(...$permissions) {
        $perm = new \CharlotteDunois\Yasmin\Models\Permissions();
        if(!empty($permissions)) {
            $perm->add(...$permissions);
        }
        
        return $this->fetchApplication()->then(function ($app) use ($perm) {
            return 'https://discordapp.com/oauth2/authorize?client_id='.$app->id.'&permissions='.$perm->bitfield.'&scope=bot';
        });
    }
    
    /**
     * Adds a "client-dependant" timer (only gets run during an established WS connection). The timer gets automatically cancelled on destroy. The callback can only accept one argument, the client.
     * @param float|int  $timeout
     * @param callable   $callback
     * @param bool       $ignoreWS
     * @return \React\EventLoop\Timer\Timer
     */
    function addTimer(float $timeout, callable $callback, bool $ignoreWS = false) {
        $timer = $this->loop->addTimer($timeout, function () use ($callback, $ignoreWS, &$timer) {
            if($ignoreWS || $this->getWSstatus() === \CharlotteDunois\Yasmin\Constants::WS_STATUS_CONNECTED) {
                $callback($this);
            }
            
            $this->cancelTimer($timer);
        });
        
        $this->timers[] = array('type' => 1, 'timer' => $timer);
        return $timer;
    }
    
    /**
     * Adds a "client-dependant" periodic timer (only gets run during an established WS connection). The timer gets automatically cancelled on destroy. The callback can only accept one argument, the client.
     * @param float|int  $interval
     * @param callable   $callback
     * @param bool       $ignoreWS  This will ignore a disconnected or (re)connecting WS connection and run the callback anyway.
     * @return \React\EventLoop\Timer\Timer
     */
    function addPeriodicTimer(float $interval, callable $callback, bool $ignoreWS = false) {
        $timer = $this->loop->addPeriodicTimer($interval, function () use ($callback, $ignoreWS) {
            if($ignoreWS || $this->getWSstatus() === \CharlotteDunois\Yasmin\Constants::WS_STATUS_CONNECTED) {
                $callback($this);
            }
        });
        
        $this->timers[] = array('type' => 0, 'timer' => $timer);
        return $timer;
    }
    
    /**
     * Cancels a timer.
     * @param \React\EventLoop\Timer\Timer  $timer
     * @return bool
     */
    function cancelTimer(\React\EventLoop\Timer\Timer $timer) {
        $timer->cancel();
        
        $key = \array_search($timer, $this->timers, true);
        if($key !== false) {
            unset($this->timers[$key]);
        }
        
        return true;
    }
    
    /**
     * Make an instance of {ClientUser} and store it.
     * @internal
     */
    function setClientUser(array $user) {
        $this->user = new \CharlotteDunois\Yasmin\Models\ClientUser($this, $user);
    }
    
    /**
     * Registers Utils which have a setLoop method.
     * @internal
     */
    function registerUtils() {
        $utils = \glob(__DIR__.'/Utils/*.php');
        foreach($utils as $util) {
            $parts = \explode('/', \str_replace('\\', '/', $util));
            $name = \substr(\array_pop($parts), 0, -4);
            $fqn = '\\CharlotteDunois\\Yasmin\\Utils\\'.$name;
            
            if(\method_exists($fqn, 'setLoop')) {
                $fqn::setLoop($this->loop);
                $this->utils[] = $fqn;
            }
        }
    }
    
    /**
     * Destroys or stops all timers from Utils (requires that they are registered as such).
     * @internal
     */
    function destroyUtils() {
        foreach($this->utils as $util) {
            if(\method_exists($util, 'destroy')) {
                $util::destroy();
            } elseif(\method_exists($util, 'stopTimer')) {
                $util::stopTimer();
            }
        }
    }
    
    /**
     * Emits an error var $for a rejected promise.
     * @internal
     */
    function handlePromiseRejection($error) {
        $this->emit('error', $error);
    }
    
    /**
     * Validates the passed client options.
     * @param array
     * @throws \InvalidArgumentException
     */
    protected function validateClientOptions(array $options) {
        $validator = \CharlotteDunois\Validation\Validator::make($options, array(
            'disableClones' => 'array',
            'disableEveryone' => 'boolean',
            'fetchAllMembers' => 'boolean',
            'messageCacheLifetime' => 'integer|min:0',
            'shardID' => 'integer|min:0',
            'shardCount' => 'integer|min:1',
            'http.restTimeOffset' => 'integer',
            'ws.compression' => 'string|boolean',
            'ws.disabledEvents' => 'array',
            'ws.largeThreshold' => 'integer|min:50|max:250',
            'ws.presence' => 'array'
        ));
        
        if($validator->fails()) {
            $errors = $validator->errors();
            
            $name = \array_keys($errors)[0];
            $error = $errors[$name];
            
            throw new \InvalidArgumentException('Client Option '.$name.' '.\lcfirst($error));
        }
    }
}