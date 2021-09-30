<?php

namespace Team51\Command;

use Team51\Helper\API_Helper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;

class Pressable_Generate_Token extends Command {
	protected static $defaultName = 'pressable-generate-token';
	private $api_helper;
	private $output;

	protected function configure() {
		$this
		->setDescription( 'Generates a Pressable token based on Client ID and Client Secret.' )
		->setHelp( 'This command allows you to generate a Pressable OAuth token for a given API Client ID and Client Secret.' )
		->addOption( 'client_id', null, InputOption::VALUE_REQUIRED, "The Client ID." )
		->addOption( 'client_secret', null, InputOption::VALUE_REQUIRED, "The Client Secret." );
	}

	protected function execute( InputInterface $input, OutputInterface $output ) {
		$this->api_helper = new API_Helper();
		$this->output     = $output;

		$client_id = $input->getOption( 'client_id' );
		$client_secret = $input->getOption( 'client_secret' );

		if ( empty( $client_id ) ) {
			$client_id = trim( readline( 'Please provide the Pressable Client ID: ' ) );
			if ( empty( $client_id ) ) {
				$output->writeln( '<error>Missing Client ID (--client_id=A1B2C3XYZ).</error>' );
				exit;
			}
		}

		if ( empty( $client_secret ) ) {
			$client_secret = trim( readline( 'Please provide the Pressable Client Secret: ' ) );
			if ( empty( $client_secret ) ) {
				$output->writeln( '<error>Missing Client Secret (--client_secret=A1B2C3XYZ).</error>' );
				exit;
			}
		}

		$output->writeln( '<comment>Generating OAuth token on Pressable.</comment>' );

		$token = $this->api_helper->get_pressable_api_token( $client_id, $client_secret );

		$output->writeln( "<info>\nPressable OAuth Token:\n{$token}\n</info>" );

		$table = new Table( $output );
		$table->setStyle( 'box-double' );
		$table->setHeaders( array( 'Key', 'Value' ) );
		$table->setRows( array(
			array( 'PRESSABLE_API_APP_CLIENT_ID', $client_id ),
			array( 'PRESSABLE_API_APP_CLIENT_SECRET', $client_secret ),
			array( 'PRESSABLE_API_OAUTH_TOKEN', $token ),
		) );
		$table->render();

		$output->writeln( "<info>\nAll done!<info>" );
	}
}
