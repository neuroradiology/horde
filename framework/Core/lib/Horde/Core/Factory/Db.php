<?php
/**
 * A Horde_Injector:: based factory for creating Horde_Db_Adapter objects.
 *
 * PHP version 5
 *
 * @category Horde
 * @package  Core
 * @author   Michael Slusarz <slusarz@horde.org>
 * @license  http://www.fsf.org/copyleft/lgpl.html LGPL
 * @link     http://pear.horde.org/index.php?package=Core
 */

/**
 * A Horde_Injector:: based factory for creating Horde_Db_Adapter objects.
 *
 * Copyright 2010-2011 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @category Horde
 * @package  Core
 * @author   Michael Slusarz <slusarz@horde.org>
 * @license  http://www.fsf.org/copyleft/lgpl.html LGPL
 * @link     http://pear.horde.org/index.php?package=Core
 */
class Horde_Core_Factory_Db extends Horde_Core_Factory_Base
{
    /**
     * Instances.
     *
     * @var array
     */
    private $_instances = array();

    /**
     * Return the DB instance.
     *
     * @param string $app  The application.
     * @param mixed $type  The type. If this is an array, this is used as
     *                     the configuration array.
     *
     * @return Horde_Db_Adapter  The singleton instance.
     * @throws Horde_Exception
     * @throws Horde_Db_Exception
     */
    public function create($app = 'horde', $type = null)
    {
        $sig = hash('md5', serialize(array($app, $type)));

        if (isset($this->_instances[$sig])) {
            return $this->_instances[$sig];
        }

        $pushed = ($app == 'horde')
            ? false
            : $GLOBALS['registry']->pushApp($app);

        $config = is_array($type)
            ? $type
            : $this->getConfig($type);

        /* Determine if we are using the base SQL config. */
        if (isset($config['driverconfig']) &&
            ($config['driverconfig'] == 'horde')) {
            $this->_instances[$sig] = $this->create();
            return $this->_instances[$sig];
        }

        // Prevent DSN from getting polluted (this only applies to
        // non-custom auth type connections. All other custom sql
        // configurations MUST be cleansed prior to passing to the
        // factory (at least until Horde 5).
        if (!is_array($type) && $type == 'auth') {
            unset($config['driverconfig'],
                  $config['query_auth'],
                  $config['query_add'],
                  $config['query_getpw'],
                  $config['query_update'],
                  $config['query_resetpassword'],
                  $config['query_remove'],
                  $config['query_list'],
                  $config['query_exists'],
                  $config['encryption'],
                  $config['show_encryption'],
                  $config['username_field'],
                  $config['password_field'],
                  $config['table']);
        }
        unset($config['umask']);

        $e = null;
        try {
            $this->_instances[$sig] = $this->createDb($config);
        } catch (Horde_Exception $e) {}

        if ($pushed) {
            $GLOBALS['registry']->popApp();
        }

        if ($e) {
            throw $e;
        }

        return $this->_instances[$sig];
    }

    /**
     */
    public function getConfig($type)
    {
        return Horde::getDriverConfig($type, 'sql');
    }

    /**
     */
    public function createDb($config)
    {
        // Split read?
        if (!empty($config['splitread'])) {
            unset($config['splitread']);
            $write_db = $this->createDb($config);
            $config = array_merge($config, $config['read']);
            $read_db = $this->createDb($config);
            return new Horde_Db_Adapter_SplitRead($read_db, $write_db);
        }

        if (!isset($config['adapter'])) {
            if (empty($config['phptype'])) {
                throw new Horde_Exception('The database configuration is missing.');
            }
            if ($config['phptype'] == 'oci8') {
                $config['phptype'] = 'oci';
            }
            if ($config['phptype'] == 'mysqli') {
                $config['adapter'] = 'mysqli';
            } elseif ($config['phptype'] == 'mysql') {
                if (extension_loaded('pdo_mysql')) {
                    $config['adapter'] = 'pdo_mysql';
                } else {
                    $config['adapter'] = 'mysql';
                }
            } else {
                $config['adapter'] = 'pdo_' . $config['phptype'];
            }
        }

        if (!empty($config['hostspec'])) {
            $config['host'] = $config['hostspec'];
        }

        $adapter = str_replace(' ', '_', ucwords(str_replace('_', ' ', basename($config['adapter']))));
        $class = 'Horde_Db_Adapter_' . $adapter;

        if (class_exists($class)) {
            unset($config['hostspec'], $config['splitread']);
            $ob = new $class($config);

            if (!isset($config['cache'])) {
                $injector = $this->_injector->createChildInjector();
                $injector->setInstance('Horde_Db_Adapter', $ob);
                $cacheFactory = $this->_injector->getInstance('Horde_Core_Factory_Cache');
                $cache = $cacheFactory->create($injector);
                $ob->setCache($cache);
            }

            if (!isset($config['logger'])) {
                $ob->setLogger($this->_injector->getInstance('Horde_Log_Logger'));
            }

            return $ob;
        }

        throw new Horde_Exception('Adapter class "' . $class . '" not found');
    }
}
