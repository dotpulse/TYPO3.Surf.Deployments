<?php

$domain          = '';		// domain.com
$username        = '';		// username
$hostname        = '';		// e.g. server.uberspace.de not username.server.uberspace.de
$sitePackageKey  = '';		// Vendor.ThemePackage
$setFlowRootpath = false;	// enable if you get internal server erros
$copyPackages    = array(	// the packages that are not managed by composer
	'Plugins' => array(  ),
	'Sites'   => array( $sitePackageKey )
);

// ------------------------------------------------------------------

$domain     = $domain.'.surf';
$projectKey = preg_replace("/[^a-zA-Z0-9]+/", "", $domain);

// Create a simple workflow based on the predefined 'SimpleWorkflow'.
$workflow = new \TYPO3\Surf\Domain\Model\SimpleWorkflow();
$workflow->setEnableRollback(TRUE);

// Workaround: If you run "migrate" directly, you get an error. After "help" everything works. Might be a bug in TYPO3 Surf
$workflow->defineTask($projectKey.':runHelp', 'typo3.surf:shell', array(
	'command' => '{releasePath}/flow help'
));
$workflow->beforeTask('typo3.surf:typo3:flow:migrate', $projectKey.':runHelp');

// Workaround: Publish images in _Resources/Persistent folder
$workflow->defineTask($projectKey.':publishImages', 'typo3.surf:shell', array(
	'command' => 'FLOW_CONTEXT=Production {releasePath}/flow media:clearthumbnails',
	'command' => 'FLOW_CONTEXT=Production {releasePath}/flow resource:clean',
	'command' => 'FLOW_CONTEXT=Production {releasePath}/flow resource:publish'
));
$workflow->afterTask('typo3.surf:typo3:flow:migrate', $projectKey.':publishImages');

// Create and configure a simple shell task to add the FLOW_CONTEXT and FLOW_ROOTPATH to your .htaccess file
$workflow->defineTask($projectKey.':editHtaccess', 'typo3.surf:shell', array(
	'command' => 'echo -e "\n'
				 . 'SetEnv FLOW_CONTEXT Production \n'
				 . ($setFlowRootpath?'SetEnv FLOW_ROOTPATH {deploymentPath}/releases/current/ \n':'')
				 . '" >> {releasePath}/Web/.htaccess'
));
$workflow->addTask($projectKey.':editHtaccess', 'finalize');

// Change composer.json to our own and copy some unpacked sources.
$workflow->defineTask($projectKey.':fixcomposer', 'typo3.surf:localshell', array(
	'command' => 'cp '.FLOW_PATH_ROOT.'composer.* '.FLOW_PATH_ROOT.'Data/Surf/Uberspace/'.$domain.'/;'
));
$workflow->afterTask('typo3.surf:package:git', $projectKey.':fixcomposer');

// Add missing files that are not managed by composer.
$addPackages = '';
foreach ($copyPackages as $folder => $packages) {
	$addPackages .= 'mkdir -p '.FLOW_PATH_ROOT.'Data/Surf/Uberspace/'.$domain.'/Packages/'.$folder.'/;';
	foreach ($packages as $package) {
		$addPackages .= 'cp -r '.FLOW_PATH_ROOT.'Packages/'.$folder.'/'.$package.' '.FLOW_PATH_ROOT.'Data/Surf/Uberspace/'.$domain.'/Packages/'.$folder.'/;';
	}
}
$workflow->defineTask($projectKey.':injectfiles', 'typo3.surf:localshell', array(
	'command' => 'rm -rf '.FLOW_PATH_ROOT.'Data/Surf/Uberspace/'.$domain.'/Packages/Plugins;'
				 . 'rm -rf '.FLOW_PATH_ROOT.'Data/Surf/Uberspace/'.$domain.'/Packages/Sites;'
				 . 'mkdir -p '.FLOW_PATH_ROOT.'Data/Surf/Uberspace/'.$domain.'/Packages/;'
				 . 'cp -Lr '.FLOW_PATH_ROOT.'Configuration '.FLOW_PATH_ROOT.'Data/Surf/Uberspace/'.$domain.'/;'
				 . 'cp -f '.FLOW_PATH_ROOT.'Web/.htaccess '.FLOW_PATH_ROOT.'Data/Surf/Uberspace/'.$domain.'/Web/.htaccess;'
				 . 'cp -f '.FLOW_PATH_ROOT.'Web/robots.txt '.FLOW_PATH_ROOT.'Data/Surf/Uberspace/'.$domain.'/Web/robots.txt;'
				 . 'rsync -a --ignore-errors '.FLOW_PATH_ROOT.'Packages/Sites/'.$sitePackageKey.'/Resources/Private/WebRoot/* '.FLOW_PATH_ROOT.'Data/Surf/Uberspace/'.$domain.'/Web/;'
				 . 'rsync -a --exclude=index.php --exclude=_Resources --exclude=robots.txt '.FLOW_PATH_ROOT.'Web/* '.FLOW_PATH_ROOT.'Data/Surf/Uberspace/'.$domain.'/Web/;'
				 . $addPackages
));
$workflow->beforeTask('typo3.surf:transfer:rsync', $projectKey.':injectfiles');

// copy production settings.yaml to shared folder
$workflow->defineTask($projectKey.':copyProductionSettings', 'typo3.surf:shell', array(
	'command' => 'if [ -f {releasePath}/Configuration/Production/Settings.yaml ]; then '
				 . 'mkdir -p {sharedPath}/Configuration/Production/;'
				 . 'cp -Lr {releasePath}/Configuration/Production/Settings.yaml {sharedPath}/Configuration/Production/;'
				 . ' fi'
));
$workflow->afterTask('typo3.surf:transfer:rsync', $projectKey.':copyProductionSettings');

// Kill running PHP processes.
$workflow->defineTask($projectKey.':killphp', 'typo3.surf:shell', array(
	'command' => 'killall -q php-cgi || true;'
));
$workflow->afterTask('typo3.surf:symlinkrelease', $projectKey.':killphp');

// Add the workflow to the deployment. The $deployment instance is created by Surf.
$deployment->setWorkflow($workflow);

// Create and configure your node / nodes (host / hosts).
$node = new \TYPO3\Surf\Domain\Model\Node('uberspace');
$node->setHostname($username.'.'.$hostname);
$node->setOption('username', $username);

// Define your application and add it to your node.
$application = new \TYPO3\Surf\Application\TYPO3\Flow($domain);
// At uberspace: create a symlink from '/var/www/virtual/[user]/html' to '[deploymentPath]/release/current/Web'
$application->setDeploymentPath('/var/www/virtual/'.$username.'/'.$domain);
$application->setOption('repositoryUrl', 'https://git.typo3.org/Neos/Distributions/Base.git');
$application->setOption('composerCommandPath', 'composer');
$application->setOption('keepReleases', '5');

$application->setOption('packageMethod', 'git');
$application->setOption('transferMethod', 'rsync');
$application->setOption('updateMethod', NULL);
$application->setOption('sitePackageKey', $sitePackageKey);

$application->addNode($node);

// remove unused task.
$deployment->onInitialize(function() use ($workflow, $application) {
    $workflow->removeTask('typo3.surf:typo3:flow:setfilepermissions');
});

// Add the application to your deployment.
$deployment->addApplication($application);
