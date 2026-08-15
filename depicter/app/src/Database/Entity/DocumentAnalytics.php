<?php
namespace Depicter\Database\Entity;

use Averta\WordPress\Database\Entity\Model;

class DocumentAnalytics extends Model
{

	protected $idColumn = 'id';

	protected $idForeign = 'source_id';

	/**
	 * Resource name.
	 *
	 * @var string
	 */
	protected $resource = 'depicter_document_analytics';

	/**
	 * Determines what fields can be saved without be explicitly.
	 *
	 * @var array
	 */
	protected $builtin = [
		'source_id',
		'event_type',
		'ip_address',
		'user_id',
		'session_id',
		'created_at'
	];

	protected $guard = [ 'id' ];

	protected $format = [
		'created_at'  => 'currentDateTime'
	];

	public function currentDateTime() {
        return gmdate('Y-m-d H:i:s', time());
    }

	public function document(){ 
		return $this->belongsTo(Document::class, 'source_id', 'id');
	}

	/**
	 * Retrieves related meta field models
	 *
	 * @return Meta|\TypeRocket\Models\Model|null
	 */
	public function meta(){
        return $this->hasMany(Meta::class, 'relation_id', $this->idColumn );
    }
}
