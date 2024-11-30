<?php

namespace Saksono\Woojurnal\Api;

defined( 'ABSPATH' ) || exit;

use Saksono\Woojurnal\Api\MekariRequest;

class JournalEntry {

	private $mekariRequest;

	public function __construct() {
		$this->mekariRequest = new MekariRequest;
	}

	public function postJournalEntry($data) {
		write_log($data);

		// Make request
		$response = $this->mekariRequest->make(
			'POST',
			'/public/jurnal/api/v1/journal_entries',
			'',
			$data
		);

		$response = json_decode($response['body']);
		write_log($response);
		return $response;
	}

	public function patchJournalEntry($journal_entry_id, $data) {
		write_log($data);

		// Make request
		$response = $this->mekariRequest->make(
			'PATCH',
			'/public/jurnal/api/v1/journal_entries/'.$journal_entry_id,
			'',
			$data
		);

		$response = json_decode($response['body']);
		write_log($response);
		return $response;
	}

	public function deleteJournalEntry( $journal_entry_id ) {
		write_log($data);

		// Make request
		$response = $this->mekariRequest->make(
			'DELETE',
			'/public/jurnal/api/v1/journal_entries/'.$journal_entry_id,
			'',
			$data
		);

		$response = json_decode($response['body']);
		write_log($response);
		return $response;
	}
}