# TYPO3.Surf.Deployments

TYPO3 Surf Deployment example scripts

## You can use it if you..

* have packages that are not managed by composer
* don't use the default TYPO3 Neos Distribution filestructure
* want use rsync

## How it works..

* setup the public-/private-key stuff
* copy your desired *Deployment.php file to 'FLOW_PATH_ROOT/Build/Surf/'
* add your settings for
  * `$domain`
  * `$username`
  * `$hostname`
  * `$sitePackageKey`
  * `$copyPackages`
* create a symlink from '/var/www/virtual/[username]/html' to '[deploymentPath]/release/current/Web'
* execute `./flow surf:deploy YourDesiredDeployment.php`

## Example Uberspace Configuration..

```php
$domain				= 'dotpulse.ch';
$username			= 'dotusr';
$hostname			= 'regulus.uberspace.de';
$sitePackageKey		= 'Dotpulse.Theme';
$copyPackages		= array(
	'Plugins'		=> array( 'Dotpulse.Base' ),
	'Sites'			=> array( $sitePackageKey )
);
```

## What's next..

* adding examples for other servers