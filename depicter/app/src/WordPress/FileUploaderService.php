<?php
namespace Depicter\WordPress;

use Averta\WordPress\File\UploadsDirectory;
use Depicter\Utility\Sanitize;
use GuzzleHttp\Psr7\UploadedFile;

class FileUploaderService
{
	public function upload( array $files ) {
		$results = [];
		$wp_upload_dir = new UploadsDirectory();
		foreach( $files as $file ) {
			if ( ! $file instanceof  UploadedFile ) {
				continue;
			}

			if ( $file->getError() ) {
				$results[ $file->getClientFilename() ] = [
					'attachment'    => 0,
					'errors'        => [
						/* translators: maximum file size allowed to upload */
						sprintf( __( 'Cannot upload the file, because max permitted file upload size is %s.', 'depicter' ), ini_get('upload_max_filesize') )
					]
				];
				continue;
			}

			$clientFileName = Sanitize::fileName( $file->getClientFilename() );
			$tmpPath = $this->resolveUploadedFilePath( $file );
			$filetype = ( ! empty( $tmpPath ) && is_readable( $tmpPath ) )
				? wp_check_filetype_and_ext( $tmpPath, $clientFileName )
				: [ 'ext' => false, 'type' => false, 'proper_filename' => false ];

			// Rejecting uploads whose extension/MIME are not allowed by WordPress
			if ( empty( $filetype['type'] ) || empty( $filetype['ext'] ) ) {
				$results[ $file->getClientFilename() ] = [
					'attachment'    => 0,
					'errors'        => [
						/* translators: mimType of uploading file */
						sprintf( __( 'Cannot upload the file, uploading %s files are not allowed.', 'depicter' ), $file->getClientMediaType() )
					]
				];
				continue;
			}

			if ( ! empty( $filetype['proper_filename'] ) ) {
				$clientFileName = Sanitize::fileName( $filetype['proper_filename'] );
			}

			$mimeType = $filetype['type'];
			$filename = $wp_upload_dir->getPath() . "/" . $clientFileName;
			$file->moveTo( $filename );
			$attachment = array(
				'guid'           => $wp_upload_dir->getUrl() . '/' . basename( $filename ),
				'post_mime_type' => $mimeType,
				'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $filename ) ),
				'post_content'   => '',
				'post_status'    => 'inherit'
			);

			$attach_id = wp_insert_attachment( $attachment, $filename );

			if ( !is_wp_error( $attach_id ) ) {
				// Make sure that this file is included, as wp_generate_attachment_metadata() depends on it.
				require_once( ABSPATH . 'wp-admin/includes/image.php' );

				// Generate the metadata for the attachment, and update the database record.
				$attach_data = wp_generate_attachment_metadata( $attach_id, $filename );
				wp_update_attachment_metadata( $attach_id, $attach_data );
				$results[ $file->getClientFilename() ] = [
					'attachment'    => $attach_id,
					'errors'        => []
				];
			} else {
				$results[ $file->getClientFilename() ] = [
					'attachment'    => 0,
					'errors'        => [
						$attach_id['error']
					]
				];
			}
		}

		return $results;
	}

	/**
	 * Resolve a readable filesystem path for an uploaded file (for filetype checks).
	 *
	 * @param UploadedFile $file
	 *
	 * @return string
	 */
	protected function resolveUploadedFilePath( UploadedFile $file ) {
		$uri = $file->getStream()->getMetadata( 'uri' );

		return ( is_string( $uri ) && $uri !== '' ) ? $uri : '';
	}

	/**
	 * Check if user can upload unfiltered Data or not
	 *
	 * @return boolean
	 */
	public function canUploadUnfilteredData() {
		$mimes = get_allowed_mime_types();  
    	return array_key_exists('svg', $mimes) && array_key_exists('json', $mimes);  
	}
}
