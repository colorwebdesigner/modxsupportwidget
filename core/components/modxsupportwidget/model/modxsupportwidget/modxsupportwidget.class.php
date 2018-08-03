<?php
/**
 * Created with PhpStorm
 * User: matdave
 * Project: modxsupportwidget
 * Date: 8/3/2018
 * https://github.com/matdave
 */

class modxSupportWidget
{
    public $modx = null;
    public $namespace = 'modxsupportwidget';
    public $cache = null;
    public $options = array();

    public function __construct(modX &$modx, array $options = array())
    {
        $this->modx =& $modx;
        $corePath = $this->getOption('core_path', $options, $this->modx->getOption('core_path', null, MODX_CORE_PATH) . 'components/modxsupportwidget/');
        $assetsUrl = $this->getOption('assets_url', $options, $this->modx->getOption('assets_url', null, MODX_ASSETS_URL) . 'components/modxsupportwidget/');
        $supportEmail = $this->getOption('support_email', $options, 'support@modxcloud.com');
        $userArray = array();
        $user = $this->modx->user;
        if(empty($user)){
            $userArray = $user->toArray();
            $profile = $user->getOne('Profile');
            if(!empty($profile)){
                $userArray = array_merge($profile->toArray(),$userArray);
            }
        }
        /* loads some default paths for easier management */
        $this->options = array_merge(array(
            'namespace' => $this->namespace,
            'corePath' => $corePath,
            'modelPath' => $corePath . 'model/',
            'widgetsPath' => $corePath . 'elements/widgets/',
            'templatesPath' => $corePath . 'templates/',
            'assetsUrl' => $assetsUrl,
            'jsUrl' => $assetsUrl . 'mgr/js/',
            'cssUrl' => $assetsUrl . 'mgr/css/',
            'connectorUrl' => $assetsUrl . 'connector.php',
            'userDetails' => $userArray
        ), $options);
        $this->modx->addPackage('modxsupportwidget', $this->getOption('modelPath'));
        $this->modx->lexicon->load('modxsupportwidget:default');
    }
    /**
     * Get a local configuration option or a namespaced system setting by key.
     *
     * @param string $key The option key to search for.
     * @param array $options An array of options that override local options.
     * @param mixed $default The default value returned if the option is not found locally or as a
     * namespaced system setting; by default this value is null.
     * @return mixed The option value or the default value specified.
     */
    public function getOption($key, $options = array(), $default = null)
    {
        $option = $default;
        if (!empty($key) && is_string($key)) {
            if ($options != null && array_key_exists($key, $options)) {
                $option = $options[$key];
            } elseif (array_key_exists($key, $this->options)) {
                $option = $this->options[$key];
            } elseif (array_key_exists("{$this->namespace}.{$key}", $this->modx->config)) {
                $option = $this->modx->getOption("{$this->namespace}.{$key}");
            }
        }
        return $option;
    }
}