<?php
/*
Notes:
- replace {USER} and {GIT_REPO_NAME} with appropriate values
- {USER} should be prefixed with "site__", e.g. site__sozitje_hrastnik_si
*/

return array(
	'deploy_secret_key' => '{DEPLOY_SECRET_KEY}', 
	'key_filename'      => realpath('/var/ssh/{USER}_deploy_key'), 
	'git_dir'           => realpath('/var/ssh/.git_{USER}'), 
	'working_dir'       => __DIR__ . '/', //must end with /
	'log_filename'      => __DIR__ . '/logs/deploy.log', 
	'repo_name'         => '{GIT_REPO_NAME}', 
	'composer_home'     => '/var/composer/{USER}', 
);
