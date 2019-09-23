<?php
/*
 * test_parse_addresses.php
 *
 * @(#) $Header: /opt2/ena/metal/mimeparser/test_parse_addresses.php,v 1.7 2011/10/29 09:11:50 mlemos Exp $
 *
 */

	require_once('rfc822_addresses.php');
	
	$parser = new rfc822_addresses_class;

	$addresses = array(
		'test@test.com',
		'"quoted test"@test.com',
		'test name <test@test.com>',
		'test.name <test@test.com>',
		'"test@name" <test@test.com>',
		'test@name <test@test.com>',
		'"test\"name" <test@test.com>',
		'test@test.com (test name)',
		'test@test.com, test name <test@test.com>',
		'Isto é um teste <test@test.com>',
		'Isto =?iso-8859-1?q?=E9_um_teste?= <test@test.com>',
		'"Isto é um teste" <test@test.com>',
		"Isto =?iso-8859-1?q?=E9_um_teste?= \t=?iso-8859-1?q?_e_isto_=E9_outro_teste?=<test@test.com>",
		'undisclosed-recipients:;',
		'undisclosed-recipients:; (some comments)',
		'mailing-list: test@test.com, test name <test@test.com>;, another test <another_test@test.com>',
		"\\\\Escape in the beginning <test@test.com>",
		"Escape in the\\\\middle <test@test.com>",
		"Escape in the end\\\\ <test@test.com>",
	);
	$c = count($addresses);
	for($a = 0; $a<$c; ++$a)
	{
		if(!$parser->ParseAddressList($addresses[$a], $parsed))
		{
			echo 'Address extraction error: '.$parser->error.' at position '.$parser->error_position."\n";
			break;
		}
		else
		{
			echo 'Parsed address: ', $addresses[$a], "\n";
			var_dump($parsed);
			for($warning = 0, Reset($parser->warnings); $warning < count($parser->warnings); Next($parser->warnings), $warning++)
			{
				$w = Key($parser->warnings);
				echo 'Warning: ', $parser->warnings[$w], ' at position ', $w, "\n";
			}
		}
	}
?>