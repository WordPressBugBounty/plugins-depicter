<?php
namespace Depicter\Services;

use Averta\WordPress\Utility\JSON;
use Averta\WordPress\Utility\Sanitize;
use GuzzleHttp\Psr7\UploadedFile;

class ImportService
{

	protected $importFolderName = 'import';

	protected $assetsFolderName = 'assets';

	/**
	 * Extract uploaded zip file and import slider
	 * @param $file
	 *
	 * @return bool
	 */
	public function unpack( $file ) {

		$wp_upload_dir = \Depicter::storage()->uploads();
		$fileSystem = \Depicter::storage()->filesystem();

		// Generate a randomized and unpredictable folder name for temporary extraction
		$randomFolder = 'temp_import_' . wp_generate_password( 16, false, false );
		$depicterUploadPath = \Depicter::storage()->getPluginUploadsDirectory() . '/';
		$extractPath = $depicterUploadPath . $randomFolder . '/';

		try {

			if ( $file instanceof UploadedFile ) {
				// Assign a random filename to the uploaded zip file instead of client filename
				$randomZipName = 'import_' . wp_generate_password( 12, false, false ) . '.zip';
				$uploadedZipFilePath = $depicterUploadPath . $randomZipName;

				if ( !is_dir( $depicterUploadPath ) ) {
					$fileSystem->mkdir( $depicterUploadPath );
				}

				// Move uploaded zip file to destination directory
				$file->moveTo( $uploadedZipFilePath );

			} else {
				$uploadedZipFilePath = $file;
				if ( ! $fileSystem->isFile( $file ) ) {
					return false;
				}
			}

			if ( !is_dir( $extractPath ) ) {
				$fileSystem->mkdir( $extractPath );
			}

			$zipFile = new \ZipArchive();
			if ( $zipFile->open( $uploadedZipFilePath ) === true ) {
				
				// Secure extraction: prevents zip slip attacks and blocks dangerous file extensions
				for ( $i = 0; $i < $zipFile->numFiles; $i++ ) {
					$filename = $zipFile->getNameIndex( $i );
					$ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );

					// Ignore executable or dangerous files
					if ( in_array( $ext, [ 'php', 'php3', 'php4', 'php5', 'phtml', 'htaccess', 'phar' ], true ) ) {
						continue;
					}

					// Prevent Zip Slip / Path Traversal vulnerability
					if ( strstr( $filename, '../' ) !== false || strstr( $filename, '..\\' ) !== false ) {
						continue;
					}

					$zipFile->extractTo( $extractPath, array( $filename ) );
				}
				
				$zipFile->close();
			} else {
				throw new \Exception('Failed to open zip archive.');
			}

			$importedAssetIDs = $this->importAssets( $fileSystem, $wp_upload_dir, $extractPath );
			$sliderID = $this->importSlider( $importedAssetIDs, $extractPath );

			// Cleanup temporary zip file and extract directory
			if ( $file instanceof UploadedFile && file_exists( $uploadedZipFilePath ) ) {
				wp_delete_file( $uploadedZipFilePath );
			}
			$fileSystem->rmdir( $extractPath, true );

			if ( \Depicter::options()->get('use_google_fonts', 'on') === 'save_locally' ) {
				$documentModel = \Depicter::document()->getModel( $sliderID )->prepare();
				\Depicter::googleFontsService()->download( $documentModel->getFontsLink() );
			}

			return $sliderID;

		} catch( \Exception $e ) {
			// Ensure cleanup occurs even if an exception is thrown
			if ( isset( $uploadedZipFilePath ) && $file instanceof UploadedFile && file_exists( $uploadedZipFilePath ) ) {
				wp_delete_file( $uploadedZipFilePath );
			}
			if ( isset( $extractPath ) && is_dir( $extractPath ) ) {
				$fileSystem->rmdir( $extractPath, true );
			}
			return false;
		}
	}

	/**
	 * Import available assets inside assets directory
	 * @param $fileSystem
	 * @param $uploadDirectory
	 * @param string $extractPath
	 *
	 * @return array $importedIDs
	 */
	protected function importAssets( $fileSystem, $uploadDirectory, $extractPath = '' ) {
		$allowedMimeTypes = array_values( get_allowed_mime_types() );
		$importedIDs = [];

		$assetsDir = $extractPath . $this->assetsFolderName;

		// Scan assets directory to import assets
		$assets = $fileSystem->scan( $assetsDir );
		if ( $assets ) {
			foreach( $assets as $asset ) {
				$assetMimeType = wp_check_filetype( $asset['name'] )['type'];
				if ( !in_array( $assetMimeType, $allowedMimeTypes, true ) ) {
					continue;
				}
				$sanitizedFileName = Sanitize::fileName( $asset['name'] );

				$fileSystem->move( $assetsDir . '/' . $asset['name'], $uploadDirectory->getPath() . "/" . $sanitizedFileName );
				$attachmentTitle = preg_replace( '/\.[^.]+$/', '', $sanitizedFileName );
				$attachment = array(
					'guid'           => $uploadDirectory->getUrl() . '/' . $sanitizedFileName,
					'post_mime_type' => $assetMimeType,
					'post_title'     => $attachmentTitle,
					'post_content'   => '',
					'post_status'    => 'inherit'
				);

				$attachID = wp_insert_attachment( $attachment, $uploadDirectory->getPath() . "/" . $sanitizedFileName );
				if ( !is_wp_error( $attachID ) ) {
					// Generate meta data for the inserted attachment
					$attachment_metadata = wp_generate_attachment_metadata( $attachID, $uploadDirectory->getPath() . "/" . $sanitizedFileName );
					wp_update_attachment_metadata( $attachID, $attachment_metadata );
					update_attached_file( $attachID, $uploadDirectory->getPath() . "/" . $sanitizedFileName );

					$attachmentTitleParts = explode( '-', $attachmentTitle );
					$oldID = end( $attachmentTitleParts );
					$importedIDs[ $oldID ] = $attachID;
				}
			}
		}

		return $importedIDs;
	}

	/**
	 * Import Slider
	 *
	 * @param $importedIDs
	 * @param string $extractPath
	 *
	 * @return mixed|null
	 * @throws \Exception
	 */
	protected function importSlider( $importedIDs, $extractPath = '' ) {
		$dataPath = $extractPath . 'data.json';
		
		if ( !\Depicter::storage()->filesystem()->exists( $dataPath ) ) {
			throw new \Exception('data.json not found in zip package.');
		}

		$data = \Depicter::storage()->filesystem()->read( $dataPath );
		$dataArray = JSON::decode( $data, true );
		$oldUploadURL = '';
		$jsonAssets = "";

		if ( ! isset( $dataArray['lastId'] ) ) {
			$content = $dataArray['content'];
			$type = $dataArray['type'] ?? 'custom';
			$oldUploadURL = $dataArray['uploadURL'];
			$jsonAssets = $dataArray['jsonAssets'];

		} else {
			$content = $data;
			$type = 'custom';
		}

		$content = preg_replace( '/"activeBreakpoint":".+?"/', '"activeBreakpoint":"default"', $content );
		preg_match_all( '/\"(source|src)\":\"(\d+)\"/', $content, $assets, PREG_SET_ORDER );
		if ( !empty( $assets ) ) {
			foreach( $assets as $asset ) {
				if ( !empty( $asset[2] ) && !empty( $importedIDs[ $asset[2] ] ) ) {
					$content = str_replace( $asset[0], '"' . $asset[1] . '":"'. $importedIDs[ $asset[2] ] .'"', $content );
				}
			}
		}

		preg_match_all( '/"src":\{[^\}]+\}/', $content, $backgroundImages, PREG_SET_ORDER );
		if ( ! empty( $backgroundImages ) ) {
			foreach( $backgroundImages as $backgroundImage ) {
				if ( ! empty( $backgroundImage[0] ) ) {
					$newBackgroundImage = $backgroundImage[0];
					$patterns = [ '"default":"(\d+)"','"tablet":"(\d+)"','"mobile":"(\d+)"'];
					foreach( $patterns as $pattern ) {
						if ( preg_match( '/' . $pattern . '/', $backgroundImage[0], $asset ) ) {
							$convertMedia = str_replace( $asset[1], $importedIDs[ $asset[1] ], $asset[0] );
							$newBackgroundImage = str_replace( $asset[0], $convertMedia, $newBackgroundImage);
						}
					}
					$content = str_replace( $backgroundImage[0], $newBackgroundImage, $content );
				}
			}
		}

		if ( !empty( $oldUploadURL ) && ! empty( $jsonAssets ) ) {
			$oldUploadURL = str_replace( "/", "\\\\\\/", $oldUploadURL );
			$pattern = "/$oldUploadURL\\\\\\/\\d+\\\\\\/\\d+\d+/";
			$newUploadURL = str_replace( "/", "\\\\\\/", \Depicter::storage()->uploads()->getUrl() );
			$content = preg_replace( $pattern, $newUploadURL, $content );
		}

		$document = \Depicter::documentRepository()->create();
		$document->update([
			'content' => $content,
			'status' => 'publish',
			'type' => $type
		]);

		return $document->id;
	}
}