<?php

namespace App\baseClasses;

use Symfony\Component\HttpFoundation\Request as HttpRequest;

class KCRequest extends HttpRequest {

	function __construct() {
		parent::__construct(
			$_GET,
			$_POST,
			[],
			$_COOKIE,
			$_FILES,
			$_SERVER
		);
	}

	public function getInputs() {

        if (strpos($this->headers->get('content-type'), 'multipart/form-data') !== false) {
            $requestArr = kcRecursiveSanitizeTextField(collect($this->request)->toArray());
            $filesArr = collect($this->files)->toArray();

            $parameters = collect([])->merge($requestArr);
            $parameters = $parameters->merge($filesArr);

            $parameters = $parameters->toArray();
        } else {
            if ( $this->getContent() ) {
                $parameters = json_decode( $this->getContent(), true );
            } else {
                $parameters = $this->query;
            }

            $parameters = kcRecursiveSanitizeTextField( $parameters );
        }

		return $parameters;
	}
}