<?php
/**
 * Created with PhpStorm
 * User: matdave
 * Project: modxsupportwidget
 * Date: 8/3/2018
 * https://github.com/matdave
 */
require_once 'vendor/autoload.php';
use DeviceDetector\DeviceDetector;
use DeviceDetector\Parser\Device\DeviceParserAbstract;

class modxSupportWidget
{
    public $modx = null;
    public $namespace = 'modxsupportwidget';
    public $cache = null;
    public $options = array();
    public $supportEmail = null;
    public $userArray = array();
    public $providerCache = array();

    public function __construct(modX &$modx, array $options = array())
    {
        $this->modx =& $modx;
        $corePath = $this->getOption('core_path', $options, $this->modx->getOption('core_path', null, MODX_CORE_PATH) . 'components/modxsupportwidget/');
        $assetsUrl = $this->getOption('assets_url', $options, $this->modx->getOption('assets_url', null, MODX_ASSETS_URL) . 'components/modxsupportwidget/');
        $this->supportEmail = $this->getOption('support_email', $options, 'help@modx.com');
        $user = $this->modx->user;
        if(!empty($user)){
            $this->userArray = $user->toArray();
            $profile = $user->getOne('Profile');
            if(!empty($profile)){
                $this->userArray = array_merge($profile->toArray(),$this->userArray);
            }
        }
        $this->options = array_merge(array(
            'namespace' => $this->namespace,
            'corePath' => $corePath,
            'modelPath' => $corePath . 'model/',
            'widgetsPath' => $corePath . 'elements/widgets/',
            'processorsPath' => $corePath . 'processors/',
            'templatesPath' => $corePath . 'templates/',
            'assetsUrl' => $assetsUrl,
            'jsUrl' => $assetsUrl . 'mgr/js/',
            'cssUrl' => $assetsUrl . 'mgr/css/',
            'connectorUrl' => $assetsUrl . 'connector.php'
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

    /**
     * @param string $tpl
     * @param array $placeholders
     * @return string
     */
    public function getFileChunk($tpl,array $placeholders = array()) {
        $output = '';
        $file = $tpl;
        if (!file_exists($file)) {
            $file = $this->modx->getOption('manager_path').'templates/'.$this->modx->getOption('manager_theme',null,'default').'/'.$tpl;
        }
        if (!file_exists($file)) {
            $file = $this->modx->getOption('manager_path').'templates/default/'.$tpl;
        }
        if (file_exists($file)) {
            /** @var modChunk $chunk */
            $chunk = $this->modx->newObject('modChunk');
            $chunk->setCacheable(false);
            $tplContent = file_get_contents($file);
            $chunk->setContent($tplContent);
            $output = $chunk->process($placeholders);
        }
        return $output;
    }

    public function getClient() {
        DeviceParserAbstract::setVersionTruncation(DeviceParserAbstract::VERSION_TRUNCATION_NONE);
        $userAgent = $_SERVER['HTTP_USER_AGENT'];
        $dd = new DeviceDetector($userAgent);
        $dd->parse();
        if ($dd->isBot()) {
            return array(
                "bot" => $dd->getBot()
            );
        }else{
            return array(
                "clientInfo" => $dd->getClient(),
                "osInfo" => $dd->getOs(),
                "device" => $dd->getDeviceName(),
                "brand" => $dd->getBrandName(),
                "model" => $dd->getModel()
            );
        }
    }

    public function checkForUpdates($package, $modx, $providerCache = array()){
        $updates = array('count' => 0);
        if ($package->get('provider') > 0 && $modx->getOption('auto_check_pkg_updates',null,false)) {
            $updateCacheKey = 'mgr/providers/updates/'.$package->get('provider').'/'.$package->get('signature');
            $updateCacheOptions = array(
                xPDO::OPT_CACHE_KEY => $modx->cacheManager->getOption('cache_packages_key', null, 'packages'),
                xPDO::OPT_CACHE_HANDLER => $modx->cacheManager->getOption('cache_packages_handler', null, $modx->cacheManager->getOption(xPDO::OPT_CACHE_HANDLER)),
            );
            $updates = $modx->cacheManager->get($updateCacheKey, $updateCacheOptions);
            if (empty($updates)) {
                /* cache providers to speed up load time */
                /** @var modTransportProvider $provider */
                if (!empty($providerCache[$package->get('provider')])) {
                    $provider =& $providerCache[$package->get('provider')];
                } else {
                    $provider = $package->getOne('Provider');
                    if ($provider) {
                        $providerCache[$provider->get('id')] = $provider;
                    }
                }
                if ($provider) {
                    $updates = $provider->latest($package->get('signature'));
                    $updates = array('count' => count($updates));
                    $modx->cacheManager->set($updateCacheKey, $updates, 1600, $updateCacheOptions);
                }
            }
        }
        return (int)$updates['count'] >= 1 ? true : false;
    }

    public function formatBytes($size, $precision = 2) {
        $base = log($size, 1024);
        $suffixes = array('', 'K', 'M', 'G', 'T');
        return round(pow(1024, $base - floor($base)), $precision) .' '. $suffixes[floor($base)];
    }

    public function getLogSize(){
        $log = $this->modx->getOption(xPDO::OPT_CACHE_PATH) . 'logs/error.log';
        $logsize = 0;
        if (file_exists($log)) {
            $logsize = $this->formatBytes(filesize($log));
        }
        return $logsize;
    }
    
    public function getPackages(){
        //Get Transport Packages
        $packs = array();
        $updates = 0;
        $c = $this->modx->newQuery('transport.modTransportPackage');
        $c->where(array('installed:IS NOT'=>null));
        $c->sortby('package_name', 'ASC');
        $c->sortby('installed', 'ASC');
        $packages = $this->modx->getCollection('transport.modTransportPackage', $c);
        if(!empty($packages)){
            foreach($packages as $p){
                $update = $this->checkForUpdates($p, $this->modx, $this->providerCache);
                $packs[$p->get('package_name')] = array(
                    'update' => $update
                    ,'package_name' => $p->get('package_name')
                    ,'version' => $p->get('version_major'). '.' . $p->get('version_minor'). '.' . $p->get('version_patch'). '-' . $p->get('release')
                    ,'installed' => $p->get('installed')
                );
            }
        }

        return array_values($packs);
    }
}