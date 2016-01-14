<?php
/**
 * This contains the code to initialise the framework from the web
 *
 * @author Lindsay Marshall <lindsay.marshall@ncl.ac.uk>
 * @copyright 2014-2016 Newcastle University
 */
/**
 * Store a new framework config item
 * @param string    $name
 * @param string    $value
 *
 * @return void
 **/
    function addfwconfig($name, $value)
    {
        $fwc = R::dispense('fwconfig');
        $fwc->name = $name;
        $fwc->value = $value;
        R::store($fwc);
    }

    set_time_limit(120); # some people have very slow laptops and they run out of time on the installer.

    include 'class/support/framework.php';
    Framework::initialise();
/*
 * Initialise template engine - check to see if it is installed!!
 *
 */
    include 'vendor/autoload.php';
/*
 * URLs for various clientside packages that are used by the installer and by the framework
 */
    $fwurls = array(
        'bootcss'   => '//maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap.min.css',
        'facss'     => '//maxcdn.bootstrapcdn.com/font-awesome/4.5.0/css/font-awesome.min.css',
        'jquery1'   => '//code.jquery.com/jquery-1.12.0.min.js',
        'jquery2'   => '//code.jquery.com/jquery-2.2.0.min.js',
        'bootjs'    => '//maxcdn.bootstrapcdn.com/bootstrap/3.3.6/js/bootstrap.min.js',
        'bootbox'   => '//cdnjs.cloudflare.com/ajax/libs/bootbox.js/4.4.0/bootbox.min.js',
        'parsley'   => 'https://cdnjs.cloudflare.com/ajax/libs/parsley.js/2.1.2/parsley.min.js',
        'parsleyr'  => 'https://cdnjs.cloudflare.com/ajax/libs/parsley.js/2.1.2/parsley.remote.js',
    );

    try
    {
        $twig = new Twig_Environment(
            new Twig_Loader_Filesystem('./install/twigs'),
            array('cache' => FALSE, 'debug' => TRUE)
        );
    }
    catch (Exception $e)
    {
        include 'install/errors/notwig.php';
        exit;
    }
/**
 * Test some PHP installation features...
 */
    $hasmb = function_exists('mb_strlen');
    $haspdo = in_array('mysql', PDO::getAvailableDrivers());

    if (!$hasmb || !$haspdo)
    {
        include 'install/errors/phpbuild.php';
        exit;
    }
/**
 * Find out where we are
 *
 * Note that there issues with symbolic linking and __DIR__ being on a different path from the DOCUMENT_ROOT
 * DOCUMENT_ROOT seems to be unresolved
 *
 * DOCUMENT_ROOT should be a substring of __DIR__ in a non-linked situation.
 */
    $dn = preg_replace('#\\\\#', '/', __DIR__); # windows installers have \ in the name
    $sdir = preg_replace('#/+$#', '', $_SERVER['DOCUMENT_ROOT']); # remove any trailing / characters
    while (strpos($dn, $sdir) === FALSE)
    { # ugh - not on the same path
        $sdn = $sdir;
        $sdr = array();
        while (!is_link($sdn) && $sdn != '/')
        {
            $pp = pathinfo($sdn);
            array_unshift($sdr, $pp['basename']);
            $sdn = $pp['dirname'];
        }
        if (is_link($sdn))
        { # not a symbolic link clearly.
            $sdir = preg_replace('#/+$#', '', readlink($sdn).'/'.implode('/', $sdr));
        }
        else
        {
            include 'install/errors/symlink.php';
            exit;
        }
    }
    $bdr = array();
    while ($dn != $sdir)
    {
        $pp = pathinfo($dn);
        $dn = $pp['dirname'];
        array_unshift($bdr, $pp['basename']);
    }
    if (empty($bdr))
    {
        $dir = '';
        $name = 'newproject';
    }
    else
    {
        $dir = '/'.implode('/', $bdr);
        $name = end($bdr); # don't use $bdr again so no need to        reset() it...
    }

    $tpl = 'install.twig';
    $vals = array('name' => $name, 'dir' => __DIR__, 'fwurls' => $fwurls);

    $fail = FALSE;
    if (preg_match('/#/', $name))
    { // names with # in them will break the regexp in Local debase()
        $fail = $vals['hashname'] = TRUE;
    }
    elseif (!function_exists('password_hash'))
    {
        $fail = $vals['phpversion'] = TRUE;
    }
    $fd = @fopen('.test', 'w');
    if ($fd === FALSE)
    {
        $fail = $vals['nodotgw'] = TRUE;
    }
    else
    {
        fclose($fd);
        unlink('.test');
    }

    $fd = @fopen('class/.test', 'w');
    if ($fd === FALSE)
    {
        $fail = $vals['noclassgw'] = TRUE;
    }
    else
    {
        fclose($fd);
        unlink('class/.test');
    }

//    $hasconfig = file_exists('class/config.php');
//    $hashtaccess  = file_exists('.htaccess');
//    $vals['hasconfig'] = $hasconfig;
//    $vals['hashtaccess'] =  $hashtaccess;
    if (!$fail && filter_has_var(INPUT_POST, 'sitename'))
    { # this is an installation attempt
        $cvars = array(
            'dbhost'        => array('DBHOST', FALSE), # name of const, add to DB, DB fieldname
            'dbname'        => array('DB', FALSE),
            'dbuser'        => array('DBUSER', FALSE),
            'dbpass'        => array('DBPW', FALSE),
            'sitename'      => array('SITENAME', TRUE),
            'siteurl'       => array('SITEURL', TRUE),
            'sitenoreply'   => array('SITENOREPLY', TRUE),
            'sysadmin'      => array('SYSADMIN', TRUE),
            'admin'         => array('', FALSE),
            'adminpw'       => array('', FALSE),
            'cadminpw'      => array('', FALSE),
        );

        $cvalue = array();
        foreach (array_keys($cvars) as $v)
        {
            if (filter_has_var(INPUT_POST, $v))
            {
                $cvalue[$v] = trim($_POST[$v]);
            }
            else
            {
                header('HTTP/1.1 400 Bad Request');
                exit;
            }
        }

/*
 * Setup the config.php file in the lib directory
 */
        $fd = fopen('class/config.php', 'w');
        if ($fd === FALSE)
        {
            header('HTTP/1.1 500 Internal Error');
            exit;
        }
        fputs($fd, '<?php'.PHP_EOL);
        fputs($fd, '/**'.PHP_EOL.' * Generated by framework installer - '.date('r').PHP_EOL.'*/'.PHP_EOL.'    class Config'.PHP_EOL.'    {'.PHP_EOL);
        fputs($fd, "\tconst BASEDNAME\t= '".$dir."';".PHP_EOL);
        foreach ($cvars as $fld => $pars)
        {
            if ($pars[0] != '')
            { # Only save relevant values - see above
                fputs($fd, "\tconst ".$pars[0]."\t= '".$cvalue[$fld]."';".PHP_EOL);
            }
        }
        fputs($fd, '/**
 * The following constant defines an array of URLs that are used in various Twigs.
 * It\'s easier to have them all here than to have to patch them in a variety of twigs.
 * The array gets passed into Twig from Local as the variable fwurls and is accessed using the relevant index.
 */
        const URLS      = array('.PHP_EOL);
        foreach ($fwurls as $k => $v)
        {
            fputs($fd, "\t'".$k."' => '".$v."',".PHP_EOL);
        }
        fputs($fd, ');'.PHP_EOL.'    }'.PHP_EOL.'?>');
        fclose($fd);
/*
 * Setup the .htaccess file
 */
        $fd = fopen('.htaccess', 'w');
        if ($fd === FALSE)
        {
            @unlink('class/config.php');
            header('HTTP/1.1 500 Internal Error');
            exit;
        }
        fputs($fd, 'RewriteEngine on'.PHP_EOL.'Options -Indexes +FollowSymlinks'.PHP_EOL);
        fputs($fd, 'RewriteBase '.($dir === '' ? '/' : $dir).PHP_EOL);
        fputs($fd, 'RewriteRule ^(ajax.*) $1 [L,NC,QSA]'.PHP_EOL.'RewriteRule ^(assets)/(.*) $1/$2 [L,NC]'.PHP_EOL.
            'RewriteRule ^.*$ index.php [L,QSA]'.PHP_EOL);
        fclose($fd);
/*
 * Try opening the database and setting up the User table
 */
        require('rb.php');
        try
        {
            $now = r::isodatetime(time() - date('Z')); # make sure the timestamp is in UTC (this should fix a weird problem with some XAMPP installations)
            $vals['dbhost'] = $cvalue['dbhost'];
            $vals['dbname'] = $cvalue['dbname'];
            $vals['dbuser'] = $cvalue['dbuser'];
            R::setup('mysql:host='.$cvalue['dbhost'].';dbname='.$cvalue['dbname'], $cvalue['dbuser'], $cvalue['dbpass']); # mysql initialiser
            R::freeze(FALSE);
            R::nuke(); # clear everything.....
            $user = R::dispense('user');
            $user->email = $cvalue['email'];
            $user->login = $cvalue['admin'];
            $user->password = password_hash($cvalue['adminpw'], PASSWORD_DEFAULT);
            $user->active = 1;
            $user->confirm = 1;
            $user->joined = $now;
            R::store($user);
/**
 * Now initialise the confirmation code table
 */
            $conf = R::dispense('confirm');
	    $conf->code = 'this is a rubbish code';
	    $conf->issued = $now;
	    $conf->kind = 'C';
	    R::store($conf);
	    $user->xownConfirm[] = $conf;
	    R::store($user);
	    R::trash($conf);
/**
 * Check that timezone setting for PHP has not made the date into the future...
 */
            $dt = R::findOne('user', 'joined > NOW()');
            if (is_object($dt))
            {
                $vals['timezone'] = TRUE;
            }
/**
 * Save some framework configuration information into the database
 * This will make it easier to remote updating of the system one
 * it is up and running
 */
            foreach ($cvars as $fld => $pars)
            {
                if ($pars[1])
                {
                    addfwconfig($fld, $cvalue[$fld]);
                }
            }
            foreach ($fwurls as $k => $v)
            {
                addfwconfig($k, $v);
            }

/**
 * See code below for significance of the entries (kind, source, admin, needlogin, devel, active)
 *
 * the link for install.php is to catch when people try to run install again after a successful install
 */
            $pages = array(
                'about'         => array(Siteaction::TEMPLATE, 'about.twig', 0, 0, 0, 1),
                'admin'         => array(Siteaction::OBJECT, 'Admin', 1, 1, 0, 1),
                'confirm'       => array(Siteaction::OBJECT, 'UserLogin', 0, 0, 0, 1),
                'contact'       => array(Siteaction::OBJECT, 'Contact', 0, 0, 0, 1),
                'devel'         => array(Siteaction::OBJECT, 'Developer', 1, 1, 1, 1),
                'error'         => array(Siteaction::OBJECT, 'Error', 0, 0, 0, 1),
                'forgot'        => array(Siteaction::OBJECT, 'UserLogin', 0, 0, 0, 1),
                'home'          => array(Siteaction::TEMPLATE, 'index.twig', 0, 0, 0, 1),
                'install.php'   => array(Siteaction::TEMPLATE, 'oops.twig', 0, 0, 0, 1),
                'login'         => array(Siteaction::OBJECT, 'UserLogin', 0, 0, 0, 1),
                'logout'        => array(Siteaction::OBJECT, 'UserLogin', 0, 1, 0, 1),
                'register'      => array(Siteaction::OBJECT, 'UserLogin', 0, 0, 0, 1),
                'robots.txt'    => array(Siteaction::OBJECT, 'Robot', 0, 0, 0, 0),
                'sitemap.xml'   => array(Siteaction::OBJECT, 'Sitemap', 0, 0, 0, 0),
            );
            foreach ($pages as $name => $data)
            {
                $p = R::dispense('page');
                $p->name = $name;
                $p->kind = $data[0];
                $p->source = $data[1];
                $p->admin = $data[2];
                $p->needlogin = $data[3];
                $p->devel = $data[4];
                $p->mobileonly = 0;
                $p->active = $data[5];
                R::store($p);
            }
/**
 * Set up some roles for access control:
 *
 * Admin for the Site
 * Developer for the Site
 *
 * These are both granted to the admin user.
 */
            $cname = R::dispense('rolecontext');
            $cname->name = 'Site';
            $cname->fixed = 1;
            R::store($cname);

            $rname = R::dispense('rolename');
            $rname->name = 'Admin';
            $rname->fixed = 1;
            R::store($rname);

            $role = R::dispense('role');
            $role->otherinfo = '-';
            $role->start = $now;
            $role->end =   $now; # this makes RedBean make it a datetime field
            R::store($role);
            $role->end = NULL; # clear end date as we don't want to time limit admin
            R::store($role);
            $user->xownRole[] = $role;
            $cname->xownRole[] = $role;
            $rname->xownRole[] = $role;
            R::store($rname);

            $rname = R::dispense('rolename');
            $rname->name = 'Developer';
            R::store($rname);

            $role = R::dispense('role');
            $role->otherinfo = '-';
            $role->start = $now;
            $role->end = NULL; # no end date
            R::store($role);
            $user->xownRole[] = $role;
            $cname->xownRole[] = $role;
            $rname->xownRole[] = $role;
            R::store($user);
            R::store($cname);
            R::store($rname);
            $tpl = 'success.twig';
        }
        catch (Exception $e)
        { # something went wrong - so cleanup and try again...
            $vals['dberror'] = $e->getMessage();
            @unlink('.htaccess');
            @unlink('class/config.php');
        }
    }
    echo $twig->render($tpl, $vals);
?>
