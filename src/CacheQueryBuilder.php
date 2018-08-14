<?php

namespace Authentik\EloquentCache;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class CacheQueryBuilder extends Builder {
	protected $model;

	public function __construct($query, Model $model) {
		$this->model = $model;

		parent::__construct($query);
	}


	public function get($columns = ['*']) {

	}


	public function update(array $values) {
		
	}


	public function delete() {

	}
}