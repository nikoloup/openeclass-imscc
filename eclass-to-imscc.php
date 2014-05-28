<?php
include('./convertCourse.php');
include('./config/config.php');
require_once 'Console/CommandLine.php';

$parser = new Console_CommandLine();
$parser->description = 'A command line tool converting eclass courses to IMS Common Cartridge format';
$parser->version = '0.1';
$parser->addOption('filename', array(
    'short_name'  => '-f',
    'long_name'   => '--file',
    'description' => 'write output to FILE',
    'help_name'   => 'FILE',
    'action'      => 'StoreString'
));
$parser->addOption('courseid', array(
    'short_name'  => '-c',
    'long_name'   => '--courseid',
    'description' => "define the course_id of the course to be converted",
    'action'      => 'StoreInt'
));
try {
    $result = $parser->parse();
    if(!isset($result->options['courseid']))
    {
	echo "ERROR: Please specify course id using -c\n";
	exit;
    }
    if(!isset($result->options['filename']))
    {
	$result->options['filename'] = "output.zip";
    }

    $cc = new ConvertCourse($dbhost,$user,$pass,$dbname,$host);
    $cc->convert($result->options['courseid'], $result->options['filename']);
    
} catch (Exception $exc) {
    $parser->displayError($exc->getMessage());
}



?>
