<?php
namespace Depicter\Database\Entity;

use Averta\WordPress\Database\Entity\Model;

class QueueJob extends Model
{
	protected $idColumn = 'id';

	/**
	 * Resource name.
	 *
	 * @var string
	 */
	protected $resource = 'depicter_queue_jobs';

	/**
	 * Determines what fields can be saved without be explicitly.
	 *
	 * @var array
	 */
	protected $builtin = [
		'attempts',
		'reserved_at',
        'available_at',
		'created_at',
		'status',
        'last_error'
	];

	protected $guard = [ 'id' ];

	protected $format = [
		'created_at'  => 'currentDateTime',
		'reserved_at'  => 'currentDateTime',
        'available_at' => 'currentDateTime',
	];

	public function currentDateTime() {
        return gmdate('Y-m-d H:i:s', time());
    }
}
