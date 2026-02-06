<?php

namespace Depicter\Database\Repository;

use Depicter\Database\Entity\QueueJob;
use Depicter\Utility\Sanitize;

class QueueJobRepository
{

    private QueueJob $job;

    public function __construct(){
        $this->job = QueueJob::new();
    }

    /**
     * Access to an instance of Lead entity
     *
     * @return QueueJob
     */
    public function job(): QueueJob{
        return QueueJob::new();
    }

    public function create( $queue, $payload ) {
        return $this->job()->create([
            'queue' => $queue,
            'payload' => $payload,
            'attempts' => 0,
        ]);
    }

    public function update( $id, array $fields = [] ) {
        if ( empty( $fields ) ) {
            return false;
        }

        $job =  $this->job()->findById( $id );

        if ( $job && $job->count() ){
            return $job->first()->update($fields);
        }

        return false;
    }

    public function delete( $id ): bool
    {
        $succeed = false;

        if( is_array( $id ) ){
            $ids = $id;
        } elseif( false !== strpos( $id, ',' ) ){
            $ids = explode(',', $id );
        } else {
            $ids = [$id];
        }

        foreach( $ids as $id ){
            $id = Sanitize::int( $id );
            if( $job = $this->job()->findById( $id ) ){
                $job->delete();
                $succeed = true;
            }
        }
        return $succeed;
    }

}
