<?php
namespace Depicter\Controllers\Ajax;


use Averta\Core\Utility\Arr;
use Depicter\Utility\Sanitize;
use Psr\Http\Message\ResponseInterface;
use WPEmerge\Requests\RequestInterface;

class DashboardAjaxController {

	/**
	 * Retrieves Lists of all entries. (GET)
	 *
	 * @param RequestInterface $request
	 * @param string           $view
	 *
	 * @return ResponseInterface
	 * @throws \Exception
	 */
	public function index(RequestInterface $request, $view)
	{

		$args = [
			'page' => !empty( $request->query('page'    ) ) ? Sanitize::int( $request->query('page') ) : 1,
			'perPage' => !empty( $request->query('perpage' ) ) ? Sanitize::int( $request->query('perpage') ) : 1000,
			'orderBy' => !empty( $request->query('orderBy' ) ) ? Sanitize::textfield( $request->query('orderBy') ) : 'modified_at',
			'order' => !empty( $request->query('order' ) ) ? Sanitize::textfield( $request->query('order') ) : 'DESC',
			's' => !empty( $request->query('s' ) ) ? Sanitize::textfield( $request->query('s') ) : '',
			'has' => !empty( $request->query('has' ) ) ? Sanitize::textfield( $request->query('has') ) : '',
			'types' => !empty( $request->query('types' ) ) ? Sanitize::textfield( $request->query('types') ) : '',
		];

		$args['has'] = $args['has'] ? explode(',', $args['has'] ) : '';

		$results = \Depicter::documentRepository()->getList( [], $args );
		$hits = $results['documents'] ?? $results;

		return \Depicter::json([
			'hasMore' => isset( $results['numberOfPages'] ) && $results['numberOfPages'] > $results['page'],
			'hits'    => Arr::camelizeKeys( $hits, '_', [], true )
		])->withStatus(200);
	}

	/**
	 * Adds a new entry. (POST)
	 *
	 * @param RequestInterface $request
	 * @param string           $view
	 *
	 * @return ResponseInterface
	 * @throws \Exception
	 */
	public function create( RequestInterface $request, $view )
	{
		// Get document type
		$type = Sanitize::textfield( $request->body( 'type', 'custom' ) );

		$document = \Depicter::documentRepository()->create( $type );

		return \Depicter::json([
			'hits'    => $document
		])->withHeader('X-Message', 'New document created successfully' )
			->withHeader('X-Document-ID', $document->getID() )
			->withStatus(200);
	}

	/**
	 * Removes an entry. (DELETE)
	 *
	 * @param RequestInterface $request
	 * @param string           $view
	 *
	 * @return ResponseInterface
	 * @throws \Exception
	 */
	public function destroy(RequestInterface $request, $view)
	{
		// Get document ID
		$id = Sanitize::textfield( $request->body('ID') );

		if( empty( $id ) ){
			return \Depicter::json([
				'errors' => ['Document ID is required.']
			])->withStatus(400);
		}

		$ids = explode(',', $id);
		$result = [];

		foreach ( $ids as $_id ) {
			$_id = Sanitize::int( $_id );
			$isDeleted = \Depicter::app()->documentRepository()->delete( $_id );

			if ( $isDeleted ) {
				\Depicter::metaRepository()->deleteAllMetaByDocumentID( $_id );
				$result['hits'][] = $_id;
				wp_delete_file( \Depicter::storage()->getPluginUploadsDirectory() . '/preview-images/' . $_id . '.png' );
				// \Depicter::action()->do( 'depicter/dashboard/after/delete', $id, $properties, $result );
				do_action( 'depicter/dashboard/after/delete', $_id );
			} else {
				$result['errors'][] = sprintf( "Document '%s' does not exist.", $_id );
			}
		}

		return \Depicter::json($result)->withStatus(200);
	}

	/**
	 * Duplicates an entry.
	 *
	 * @param RequestInterface $request
	 * @param string           $view
	 *
	 * @return ResponseInterface
	 * @throws \Exception
	 */
	public function duplicate(RequestInterface $request, $view)
	{
		// Get document ID
		$id = Sanitize::int( $request->body('ID') );

		if( empty( $id ) ){
			return \Depicter::json([
				'errors' => ['Document ID is required.']
			])->withStatus(400);
		}

		$result = \Depicter::app()->documentRepository()->duplicate( $id, true );

		if( ! $result ){
			return \Depicter::json([
				'errors' => ['Document does not exist.']
			])->withHeader('X-Document-ID', $id)->withStatus(404);
		}

		return \Depicter::json([
			'hits' => Arr::camelizeKeys( $result->getProperties(), '_', [], false )
		])->withStatus(200);
	}

	/**
	 * Changes a document name.
	 *
	 * @param RequestInterface $request
	 * @param string           $view
	 *
	 * @return ResponseInterface
	 */
	public function changeName(RequestInterface $request, $view)
	{
		// Get document ID
		$id = Sanitize::int( $request->body('ID') );

		if( empty( $id ) ){
			return \Depicter::json([
				'errors' => ['Document ID is required.']
			])->withStatus(400);
		}

		// Get new name
		$newName = Sanitize::textfield( $request->body('name') );

		if( empty( $newName ) ){
			return \Depicter::json([
				'errors' => ['New document name is required.']
			])->withStatus(400);
		}

		$result = \Depicter::app()->documentRepository()->rename( $id, $newName );

		if( ! $result ){
			return \Depicter::json([
				'errors' => ['Document does not exist.']
			])->withHeader('X-Document-ID', $id)->withStatus(404);
		}

		do_action( 'depicter/dashboard/after/rename', $id, $newName );

		return \Depicter::json([
			'hits' => $result
		])->withStatus(200);
	}

	/**
	 * Changes a document slug.
	 *
	 * @param RequestInterface $request
	 * @param string           $view
	 *
	 * @return ResponseInterface
	 */
	public function changeSlug(RequestInterface $request, $view)
	{
		// Get document ID
		$id = Sanitize::int( $request->body('ID') );

		if( empty( $id ) ){
			return \Depicter::json([
				'errors' => ['Document ID is required.']
			])->withStatus(400);
		}

		// Get new name
		$newSlug = Sanitize::textfield( $request->body('slug') );

		if( empty( $newSlug ) ){
			return \Depicter::json([
				'errors' => ['New document slug is required.']
			])->withStatus(400);
		}
		try{
			$result = \Depicter::app()->documentRepository()->changeSlug( $id, $newSlug );

			if( ! $result ){
				return \Depicter::json([
					'errors' => ['Document does not exist.']
				])->withHeader('X-Document-ID', $id)->withStatus(404);
			}

			return \Depicter::json([
				'hits' => $result
			])->withStatus(200);

		} catch ( \Exception $exception ) {
			return \Depicter::json([
				'errors' => [ $exception->getMessage()]
			]);
		}
	}

	/**
	 * Imports a document data.
	 *
	 * @param RequestInterface $request
	 * @param string           $view
	 *
	 * @return ResponseInterface
	 */
	public function import(RequestInterface $request, $view)
	{
		return \Depicter::json([
			'errors' => ['Under construction!']
		])->withStatus(404);
	}

	/**
	 * Exports a document to file.
	 *
	 * @param RequestInterface $request
	 * @param string           $view
	 *
	 * @return ResponseInterface
	 */
	public function export(RequestInterface $request, $view)
	{
		// Get document ID
		$ids = Sanitize::textfield( $request->body('ID') );

		if( is_null( $ids ) ){
			return \Depicter::json([
				'errors' => ['Document ID is required.']
			])->withStatus(400);
		}

		return \Depicter::json([
			'errors' => ['Under construction!']
		])->withStatus(404);
	}


	/**
	 * Flush all documents cache
	 *
	 * @param RequestInterface $request
	 * @param string           $view
	 *
	 * @return ResponseInterface
	 */
	public function flushDocumentsCache( RequestInterface $request, $view )
	{
		try{
			$documents = \Depicter::documentRepository()->select( [ 'id' ] )->findAll()->get();
			if ( $documents )  {
				$documents = $documents->toArray();
				foreach( $documents as $document ) {
					\Depicter::front()->render()->flushDocumentCache( $document['id'] );
				}
			}

			return \Depicter::json([
				'success' => __( 'Flushed Successfully', 'depicter' )
			])->withStatus(200);
		} catch( \Exception $e ){
			return \Depicter::json([
				'errors' => [ $e->getMessage() ]
			])->withStatus(404);
		}
	}

}
