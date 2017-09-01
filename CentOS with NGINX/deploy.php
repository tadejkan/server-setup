<?php
/*
Notes:
- replace {USER} and {GIT_REPO_NAME} with appropriate values
- {USER} should be prefixed with "site__", e.g. site__sozitje_hrastnik_si
*/

/*
TO FUNCTION CORRECTLY, THIS SCRIPT REQUIRES:

- installed Composer globally:
	php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
	#php -r "if (hash_file('SHA384', 'composer-setup.php') === '55d6ead61b29c7bdee5cccfb50076874187bd9f21f65d8991d46ec5cc90518f447387fb9f76ebae1fbbacf329e583e30') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;"
	php composer-setup.php --install-dir=/usr/local/bin --filename=composer
	php -r "unlink('composer-setup.php');"
*/

set_time_limit(0);

$my_secret = trim(file_get_contents('../.deploy_secret'));

$key           = realpath('/var/ssh/{USER}_deploy_key');
$git_dir       = realpath('/var/ssh/.git_{USER}');
$work_dir      = realpath('../' . '/');
$log_file      = '../storage/logs/deploy.log';
$repo_name     = '{GIT_REPO_NAME}';
$composer_home = '/var/composer/{USER}';

$log = '';

if (!array_key_exists('payload', $_POST))
{
	exit('payload missing');
}

$payload = json_decode($_POST['payload']);

$payload_secret    = $payload->secret;
$payload_ref       = $payload->ref;
$payload_repo_name = $payload->repository->name;

if ($payload_secret != $my_secret || 
	//$payload_ref != 'refs/heads/master' || //CURRENTLY COMMENTED-OUT, since merges from dev to master show up as /refs/heads/dev!
	$payload_repo_name != $repo_name)
{
	exit('not interested');
}

$output = `ssh-agent bash -c 'ssh-add {$key}; git --git-dir={$git_dir} --work-tree={$work_dir} reset --hard; git --git-dir={$git_dir} --work-tree={$work_dir} pull' 2>&1`;
$log .= $output . "\n\n";

file_put_contents($log_file, '[' . date(DATE_ISO8601) . '] ran git pull, output: ' . $output . "\n\n", FILE_APPEND);

preg_match_all("/error: The following untracked working tree files would be overwritten by merge:(.*)" . 
	"Please move or remove them before you can merge/sU", $output, $arr);
if (sizeof($arr) >= 2 && sizeof($arr[1]) >= 1 && strlen(trim($arr[1][0])) > 0)
{
	$files_to_delete = explode("\n", trim($arr[1][0]));
	
	foreach ($files_to_delete as $file)
	{
		$file = realpath($work_dir . $file);
		$output = `rm -f {$file} 2>&1`;
		
		file_put_contents($log_file, '[' . date(DATE_ISO8601) . '] deleted file: ' . $file . ", output: " . 
			$output . "\n\n", FILE_APPEND);
	}
	
	if (sizeof($files_to_delete) > 0) //retry
	{
		$output .= `ssh-agent bash -c 'ssh-add {$key}; git --git-dir={$git_dir} --work-tree={$work_dir} reset --hard; git --git-dir={$git_dir} --work-tree={$work_dir} pull' 2>&1`;
		$log .= $output . "\n\n";
		
		file_put_contents($log_file, '[' . date(DATE_ISO8601) . '] retried git pull, output: ' . $output . "\n\n", FILE_APPEND);
	}
}

$output = `cd ..; export COMPOSER_HOME={$composer_home} && composer install --no-dev 2>&1`;
$log .= $output . "\n\n";
file_put_contents($log_file, '[' . date(DATE_ISO8601) . '] executed composer install: ' . $output . "\n\n", FILE_APPEND);
$output = `cd ..; php artisan vendor:publish 2>&1`;
$log .= $output . "\n\n";
file_put_contents($log_file, '[' . date(DATE_ISO8601) . '] executed vendor publish: ' . $output . "\n\n", FILE_APPEND);
$output = `cd ..; php artisan migrate --force 2>&1`;
$log .= $output . "\n\n";
file_put_contents($log_file, '[' . date(DATE_ISO8601) . '] executed forced migration: ' . $output . "\n\n", FILE_APPEND);

echo $log;
