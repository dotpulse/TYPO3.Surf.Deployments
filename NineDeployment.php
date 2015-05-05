<?php

$domain				= '';		// domain.com
$deploymentFolder	= $domain;	// deployment folder in /home/www-data/
$username			= '';		// usrnam
$hostname			= '';		// e.g. server.uberspace.de not username.server.uberspace.de
$sitePackageKey		= '';		// MyCustom.ThemePackage
$setFlowRootpath	= false;	// enable if you get internal server erros
$copyPackages		= array(	// the packages that are not managed by composer
	'Plugins'		=> array(  ),
	'Sites'			=> array( $sitePackageKey )
);


// ------------------------------------------------------------------

$domain				= $domain.'.surf';
$projectKey			= preg_replace("/[^a-zA-Z0-9]+/", "", $domain);

// Create a simple workflow based on the predefined 'SimpleWorkflow'.
$workflow = new \TYPO3\Surf\Domain\Model\SimpleWorkflow();
$workflow->setEnableRollback(TRUE);

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
	'command' => 'cp '.FLOW_PATH_ROOT.'composer.* '.FLOW_PATH_ROOT.'Data/Surf/NineDeployment/'.$domain.'/;'
));
$workflow->afterTask('typo3.surf:package:git', $projectKey.':fixcomposer');

// Add missing files that are not managed by composer.
$addPackages = '';
foreach ($copyPackages as $folder => $packages) {
	$addPackages .= 'mkdir -p '.FLOW_PATH_ROOT.'Data/Surf/NineDeployment/'.$domain.'/Packages/'.$folder.'/;';
	foreach ($packages as $package) {
		$addPackages .= 'cp -r '.FLOW_PATH_ROOT.'Packages/'.$folder.'/'.$package.' '.FLOW_PATH_ROOT.'Data/Surf/NineDeployment/'.$domain.'/Packages/'.$folder.'/;';
	}
}
$workflow->defineTask($projectKey.':injectfiles', 'typo3.surf:localshell', array(
	'command' => 'mkdir -p '.FLOW_PATH_ROOT.'Data/Surf/NineDeployment/'.$domain.'/Packages/;'
				 . 'cp -Lr '.FLOW_PATH_ROOT.'Configuration '.FLOW_PATH_ROOT.'Data/Surf/NineDeployment/'.$domain.'/;'
				 . 'rsync -a --exclude=index.php --exclude=_Resources '.FLOW_PATH_ROOT.'Web/* '.FLOW_PATH_ROOT.'Data/Surf/NineDeployment/'.$domain.'/Web/;'
				 . $addPackages
));
$workflow->beforeTask('typo3.surf:transfer:rsync', $projectKey.':injectfiles');

// Kill running PHP processes.
$workflow->defineTask($projectKey.':killphp', 'typo3.surf:shell', array(
	'command' => 'killall -q php-cgi || true;'
));
$workflow->afterTask('typo3.surf:symlinkrelease', $projectKey.':killphp');

// Add the workflow to the deployment. The $deployment instance is created by Surf.
$deployment->setWorkflow($workflow);

// Create and configure your node / nodes (host / hosts).
$node = new \TYPO3\Surf\Domain\Model\Node('nine');
$node->setHostname($hostname);
$node->setOption('username', $username);

// Define your application and add it to your node.
$application = new \TYPO3\Surf\Application\TYPO3\Flow($domain);
// At nine.ch: sudo nine-manage-vhosts virtual-host update [domain] --webroot=/home/www-data/[deploymentFolder]/release/current/Web
$application->setDeploymentPath('/home/www-data/'.$deploymentFolder);
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
