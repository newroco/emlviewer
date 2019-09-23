<?php

/*
 * test.php
 *
 * @(#) $Id: test.php,v 1.9 2012/03/11 08:20:25 mlemos Exp $
 *
 */

	$message_file='sample/message.eml';
	$mbox_file='sample/mbox.eml';
	$noendbreak_message_file='sample/noendbreak.eml';
	$mixedlinebreaks_message_file='sample/mixedlinebreaks.eml';
	$missingheaderseparator_message_file = 'sample/missingheaderseparator.eml';
	$q_encoding_message_file = 'sample/q-encoding.eml';
	$long_header_message_file = 'sample/longheader.eml';
	$message_data=$noendbreak_message_data='';
	if(!($file=fopen($message_file, 'rb')))
		die($message_file.' file does not exist');
	while(!feof($file))
		$message_data.=fread($file,8000);
	fclose($file);
	if(!($file=fopen($noendbreak_message_file, 'rb')))
		die($noendbreak_message_file.' file does not exist');
	while(!feof($file))
		$noendbreak_message_data.=fread($file,8000);
	fclose($file);
	$__tests=array(
		'missingheaderseparator'=>array(
			'script'=>'../test_message_decoder.php',
			'generatedfile'=>'generated/missingheaderseparator.txt',
			'expectedfile'=>'expect/missingheaderseparator.txt',
			'options'=>array(
				'parameters'=>array(
					'File'=>$missingheaderseparator_message_file,
				),
				'mbox'=>0
			)
		),
		'mbox'=>array(
			'script'=>'../test_message_decoder.php',
			'generatedfile'=>'generated/mbox.txt',
			'expectedfile'=>'expect/mbox.txt',
			'options'=>array(
				'parameters'=>array(
					'File'=>$mbox_file,
				),
				'mbox'=>1
			)
		),
		'normal'=>array(
			'script'=>'../test_message_decoder.php',
			'generatedfile'=>'generated/normal.txt',
			'expectedfile'=>'expect/normal.txt',
			'options'=>array(
				'parameters'=>array(
					'File'=>$message_file,
					'SkipBody'=>1,
				)
			)
		),
		'noendbreak'=>array(
			'script'=>'../test_message_decoder.php',
			'generatedfile'=>'generated/normal.txt',
			'expectedfile'=>'expect/normal.txt',
			'options'=>array(
				'parameters'=>array(
					'File'=>$noendbreak_message_file,
					'SkipBody'=>1,
				)
			)
		),
		'nomboxnormal'=>array(
			'script'=>'../test_message_decoder.php',
			'generatedfile'=>'generated/normal.txt',
			'expectedfile'=>'expect/normal.txt',
			'options'=>array(
				'parameters'=>array(
					'File'=>$message_file,
					'SkipBody'=>1,
				),
				'mbox'=>0
			)
		),
		'nomboxnoendbreak'=>array(
			'script'=>'../test_message_decoder.php',
			'generatedfile'=>'generated/normal.txt',
			'expectedfile'=>'expect/normal.txt',
			'options'=>array(
				'parameters'=>array(
					'File'=>$noendbreak_message_file,
					'SkipBody'=>1,
				),
				'mbox'=>0
			)
		),
		'normalfromdata'=>array(
			'script'=>'../test_message_decoder.php',
			'generatedfile'=>'generated/normal.txt',
			'expectedfile'=>'expect/normal.txt',
			'options'=>array(
				'parameters'=>array(
					'Data'=>$message_data,
					'SkipBody'=>1,
				)
			)
		),
		'noendbreakfromdata'=>array(
			'script'=>'../test_message_decoder.php',
			'generatedfile'=>'generated/normal.txt',
			'expectedfile'=>'expect/normal.txt',
			'options'=>array(
				'parameters'=>array(
					'Data'=>$noendbreak_message_data,
					'SkipBody'=>1,
				)
			)
		),
		'mixedlinebreaks'=>array(
			'script'=>'../test_message_decoder.php',
			'generatedfile'=>'generated/mixedlinebreaks.txt',
			'expectedfile'=>'expect/mixedlinebreaks.txt',
			'options'=>array(
				'parameters'=>array(
					'File'=>$mixedlinebreaks_message_file,
					'SkipBody'=>1,
				)
			)
		),
		'q-encoding'=>array(
			'script'=>'../test_message_decoder.php',
			'generatedfile'=>'generated/q-encoding.txt',
			'expectedfile'=>'expect/q-encoding.txt',
			'options'=>array(
				'parameters'=>array(
					'File'=>$q_encoding_message_file,
					'SkipBody'=>1,
				)
			)
		),
		'parseaddresses'=>array(
			'script'=>'../test_parse_addresses.php',
			'generatedfile'=>'generated/parse_addresses.txt',
			'expectedfile'=>'expect/parse_addresses.txt',
		),
		'longheader'=>array(
			'script'=>'../test_message_decoder.php',
			'generatedfile'=>'generated/longheader.txt',
			'expectedfile'=>'expect/longheader.txt',
			'options'=>array(
				'parameters'=>array(
					'File'=>$long_header_message_file,
					'SkipBody'=>1,
				)
			)
		),
	);


	define('__TEST',1);
	if(IsSet($_SERVER['argv'])
	&& GetType($_SERVER['argv']) == 'array'
	&& Count($_SERVER['argv']) > 1)
	{
		$__few = array();
		for($__a = 1; $__a < count($_SERVER['argv']); ++$__a)
		{
			$__name = $_SERVER['argv'][$__a];
			if(!IsSet($__tests[$__name]))
				die($__name." is not a valid test name.\n");
			$__few[$__name] = $__tests[$__name];
		}
		$__tests = $__few;
	}
	for($__different=$__test=$__checked=0, Reset($__tests); $__test<count($__tests); Next($__tests), $__test++)
	{
		$__name=Key($__tests);
		$__script=$__tests[$__name]['script'];
		if(!file_exists($__script))
		{
			echo "\n".'Test script '.$__script.' does not exist.'."\n".str_repeat('_',80)."\n";
			continue;
		}
		echo 'Test "'.$__name.'": ... ';
		flush();
		if(IsSet($__tests[$__name]['options']))
			$__test_options=$__tests[$__name]['options'];
		else
			$__test_options=array();
		ob_start();
		require($__script);
		$output=ob_get_contents();
		ob_end_clean();
		$generated=$__tests[$__name]['generatedfile'];
		if(!($file = fopen($generated, 'wb')))
			die('Could not create the generated output file '.$generated."\n");
		if(!fputs($file, $output)
		|| !fclose($file))
			die('Could not save the generated output to the file '.$generated."\n");
		$expected=$__tests[$__name]['expectedfile'];
		if(!file_exists($expected))
		{
			echo "\n".'Expected output file '.$expected.' does not exist.'."\n".str_repeat('_',80)."\n";
			continue;
		}
		$diff=array();
		exec('diff '.$expected.' '.$generated, $diff);
		if(count($diff))
		{
			echo "FAILED\n".'Output of script '.$__script.' is different from the expected file '.$expected." .\n".str_repeat('_',80)."\n";
			for($line=0; $line<count($diff); $line++)
				echo $diff[$line]."\n";
			echo str_repeat('_',80)."\n";
			flush();
			$__different++;
		}
		else
			echo "OK\n";
		$__checked++;
	}
	echo $__checked.' test '.($__checked==1 ? 'was' : 'were').' performed, '.($__checked!=$__test ? (($__test-$__checked==1) ? ' 1 test was skipped, ' : ($__test-$__checked).' tests were skipped, ') : '').($__different ? $__different.' failed' : 'none has failed').'.'."\n";

?>