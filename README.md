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
* point the webrequests to '[deploymentPath]/release/current/Web'
* execute `./flow surf:deploy YourDesiredDeployment`

## Example Uberspace Configuration..

```php
$domain         = 'dotpulse.ch';
$username       = 'dotusr';
$hostname       = 'regulus.uberspace.de';
$sitePackageKey = 'Dotpulse.Theme';
$copyPackages   = array(
    'Plugins'   => array( 'Dotpulse.Base' ),
    'Sites'     => array( $sitePackageKey )
);
```
Create a folder /var/www/virtual/[dotusr]/[dotpulse.ch].surf,
and a symlink from '/var/www/virtual/[dotusr]/html' to '/var/www/virtual/[dotusr]/[dotpulse.ch].surf/release/current/Web'.

## Troubleshooting

### Connection timed out

#### on Debian execute:
```
echo "ControlMaster auto
ControlPath /tmp/ssh_mux_%h_%p_%r
ControlPersist 600" | sudo tee -a /etc/ssh/ssh_config
/etc/init.d/ssh restart;
```

#### on OS X add to the file '/private/etc/sshd_config' this:
```
ControlMaster auto
ControlPath /tmp/ssh_mux_%h_%p_%r
ControlPersist 600
```
and restart the SSH: `launchctl stop com.openssh.sshd; launchctl start com.openssh.sshd`

## What's next..

* adding examples for other servers

## Thanks to..

[beelbrecht](https://gist.github.com/beelbrecht), [karsten](http://karsten.dambekalns.de/blog/using-ssh-controlmaster-with-typo3-surf.html) and [mario](https://github.com/mrimann).
