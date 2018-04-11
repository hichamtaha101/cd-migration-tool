<?php // Silence is golden
include_once( dirname( __FILE__ ) . '/wpdb.php' );
include_once( dirname( __FILE__ ) . '/formatting.php' );

class Chrome_Data_API {

	private $account_login;
	private $number;
	private $secret;

	public $country_code;
	public $language;

	private $soap;

	function __construct( $country_code ) {

		$this->number   = '308652';
		$this->secret   = '8343ac07bb984bcb';
		$this->api_url  = 'http://services.chromedata.com/Description/7b?wsdl';
		$this->language = 'en';

		$this->country_code = $country_code;

		$this->account_info = array(
			'number'        => $this->number,
			'secret'        => $this->secret,
			'country'       => $this->country_code,
			'language'  => $this->language,
		);

		$this->soap_args = array(
			'accountInfo' => $this->account_info,
		);

		$this->soap  = new SoapClient( $this->api_url );
		$this->years = $this->get_years( 2 );

	}

	public function soap_call( $function, $args = array(), $all_years = false ) {

		$params = $args;
		$params['accountInfo'] = $this->account_info;

		$soap_response = $this->soap->__soapCall( $function, array( $params ) );

		$response = new stdClass();
		$response->response = $soap_response;
		$response->parameters = $args;

		return $response;

	}

	protected function soap_call_loop( $function, $args = array() ) {

		$args = $this->append_with_year_parameter( $args );

		$response = array();

		foreach ( $args as $parameters ) {

			$api_call = $this->soap_call(
				$function,
				$parameters
			);

			$response[] = $api_call;

		}

		return $response;

	}

	/**
	 * This function retrieves the last three years for which data is available
	 * in the chrome data feed in descending order.
	 *
	 * This function can also be used to create dynamic arrays to retrieve
	 * data from chrome API using other filters for every year as set by $range
	 *
	 * @param int $range The number of years
	 *
	 * @return array The last x years set by $range
	 */
	private function get_years( $range = 4 ) {

		$soap_response = $this->soap_call( 'getModelYears' );
		$response = $soap_response->response;

		return array_slice(
			$response->modelYear,
			-$range
		);

	}

	/**
	 * This function creates a new array with the parameters provided in $args
	 * along with a year parameter for which we need the data from the chrome API
	 * as set by $this->years variable.
	 *
	 * @param array $args The parameters to increment with the year parameter
	 *
	 * @return array An array with all the parameters along with the year field
	 */
	private function append_with_year_parameter( $args = array() ) {

		$parameters = array();

		foreach ( $this->years as $year ) {

			$year_parameter = array( 'modelYear' => $year );
			$parameters[] = array_merge( $year_parameter, $args );

		}

		return $parameters;

	}

	protected function truncate_response_parameters( $data, $args ) {

		$truncated_data = array();
		$response_data = array_column( $data, 'response' );

		foreach ( $response_data as $response ) {
			$truncated_data[] = $response->{$args['property']};
		}

		foreach ( $truncated_data as $key => $value ) {
			if ( is_object( $value ) ) {
				$truncated_data[ $key ] = array( $value );
			}
		}

		if ( array_key_exists( 'unique', $args ) ) {
			$truncated_data = array_values(
				array_unique(
					call_user_func_array( 'array_merge', $truncated_data ),
					SORT_REGULAR
				)
			);
		}
		return $truncated_data;
	}
}

class Convertus_DB_Updater extends Chrome_Data_API {

	public $db;

	// The properties for style property sanitiziation into convertus api from chrome
	private $style_properties;
	private $engine_properties;

	function __construct( $country_code ) {

		parent::__construct( $country_code );
		$this->db = new WPDB();

		$this->style_properties = array(
			array(
				'property' 	=> 'id',
				'field' 		=> 'style_id',
			),
			array(
				'property' 	=> 'acode',
				'field' 		=> 'acode',
				'value' 		=> '_',
			),
			array(
				'property' 	=> 'mfrModelCode',
				'field' 		=> 'model_code',
			),
			array(
				'property' 	=> 'modelYear',
				'field' 		=> 'model_year',
			),
			array(
				'property' 	=> 'division',
				'field' 		=> 'division',
				'value' 		=> '_',
			),
			array(
				'property' 	=> 'subdivision',
				'field' 		=> 'subdivision',
				'value' 		=> '_',
			),
			array(
				'property' 	=> 'model',
				'field' 		=> 'model_name',
				'value' 		=> '_',
			),
			array(
				'property' 	=> 'trim',
				'field' 		=> 'trim',
			),
			array(
				'property' 	=> 'bodyType',
				'field' 		=> 'body_type',
				'value' 		=> '_',
			),
			array(
				'property' 	=> 'marketClass',
				'field' 		=> 'market_class',
				'value' 		=> '_',
			),
			array(
				'property' 	=> 'basePrice',
				'field' 		=> 'msrp',
				'value' 		=> 'msrp',
			),
			array(
				'property' 	=> 'drivetrain',
				'field' 		=> 'drivetrain',
			),
			array(
				'property' 	=> 'passDoors',
				'field' 		=> 'doors',
			),
		);

		$this->engine_properties = array(
			array(
				'property' => 'engineType',
				'field' => 'engine_type',
				'value' => '_',
			),
			array(
				'property' => 'fuelType',
				'field' => 'fuel_type',
				'value' => '_',
			),
			array(
				'property' => 'horsepower',
				'field' => 'horsepower',
			),
			array(
				'property' => 'netTorque',
				'field' => 'net_torque',
			),
			array(
				'property' => 'cylinders',
				'field' => 'cylinders',
			),
			array(
				'property' => 'fuelEconomy',
				'field' => 'fuel_economy',
			),
			array(
				'property' => 'fuelEconomy',
				'field' => 'fuel_economy_city_low',
			),
			array(
				'property' => 'fuelEconomy',
				'field' => 'fuel_economy_hwy_low',
			),
			array(
				'property' => 'fuelEconomy',
				'field' => 'fuel_economy_city_high',
			),
			array(
				'property' => 'fuelEconomy',
				'field' => 'fuel_economy_hwy_high',
			),
			array(
				'property' => 'fuelCapacity',
				'field' => 'fuel_capacity',
			),
			array(
				'property' => 'forcedInduction',
				'field' => 'forced_induction',
				'value' => '_',
			),
			array(
				'property' => 'displacement',
				'field' => 'displacement',
				'value' => array( '_', 'unit' ),
			),
		);

		$this->image_gallery_properties = array(
			array(
				'property' => 'url',
				'field' => 'url',
			),
			array(
				'property' => 'width',
				'field' => 'width',
			),
			array(
				'property' => 'height',
				'field' => 'height',
			),
			array(
				'property' => 'shotCode',
				'field' => 'shot_code',
			),
			array(
				'property' => 'backgroundDescription',
				'field' => 'background_description',
			),
			array(
				'property' => 'styleId',
				'field' => 'style_id',
			),
		);

	}

	public function get_divisions() {

		$soap_response = $this->soap_call_loop( 'getDivisions' );

		$divisions = array();

		foreach ( $soap_response as $response ) {

			if ( is_array( $response->response->division ) ) {

				foreach ( $response->response->division as $division ) {

					$division->name = $division->_;
					$division->image = 'http://api.convertus.com/assets/logos/' . sanitize_title_with_dashes( $division->_ ) . '.png';
					unset( $division->_ );

					$divisions[] = $division;

				}
			} else if ( is_object( $response->response->division ) ) {

				$obj = new stdClass();

				$division = $response->response->division;
				$division->name = $division->_;
				$division->image = 'http://api.convertus.com/assets/logos/' . sanitize_title_with_dashes( $division->_ ) . '.png';
				unset( $division->_ );
				$divisions[] = $response->response->division;

			}
		}

		return array_values( array_intersect_key( $divisions, array_unique( array_column( $divisions, 'id' ) ) ) );

	}

	public function update_divisions() {

		$divisions = $this->get_divisions();

		$query = 'INSERT division ( division_name, division_id, oem_logo, last_updated ) VALUES ';
		$sql_values = array();

		foreach ( $divisions as $division ) {
			$sql_values[] = "('{$division->name}', {$division->id}, '{$division->image}', now())";
		}

		$query .= implode( ',', $sql_values );

		$this->db->query( 'TRUNCATE division' );
		$this->db->query( $query );

	}

	public function get_models( $division_id = -1 ) {

		if ( $division_id === -1 ) {
			$divisions = $this->db->get_results( 'SELECT * FROM division' );
		} else {
			$divisions = $this->db->get_results( "SELECT * FROM division where division_id = {$division_id}" );
		}

		if ( $divisions ) {
			$soap_response = array();
			foreach ( $divisions as $division ) {
				$soap_response[] = $this->soap_call_loop(
					'getModels',
					array(
						'divisionName' => $division->division_name,
						'divisionId' => $division->division_id,
					)
				);
			}
		}

		$models = array();

		foreach ( $soap_response as $first_response ) {

			foreach ( $first_response as $response ) {

				if ( $response->response->responseStatus->responseCode === 'Successful' ) {

					if ( is_array( $response->response->model ) ) {

						foreach ( $response->response->model as $model ) {

							$model->name = $model->_;
							$model->year = $response->parameters['modelYear'];
							$model->division_name = $response->parameters['divisionName'];
							$model->division_id = $response->parameters['divisionId'];
							unset( $model->_ );

							$models[] = $model;

						}
					} else if ( is_object( $response->response->model ) ) {

						$model = $response->response->model;
						$model->name = $model->_;
						$model->year = $response->parameters['modelYear'];
						$model->division_name = $response->parameters['divisionName'];
						$model->division_id = $response->parameters['divisionId'];
						unset( $model->_ );
						$models[] = $model;

					}
				}
			}
		}

		return $models;

	}

	public function update_models() {

		$models = $this->get_models();

		$query = 'INSERT model ( model_year, model_name, model_id, division_name, division_id, last_updated ) VALUES ';
		$sql_values = array();

		foreach ( $models as $model ) {
			$sql_values[] = "({$model->year}, '{$model->name}', {$model->id}, '{$model->division_name}', {$model->division_id}, now())";
		}
		$query .= implode( ',', $sql_values );

		$this->db->query( 'TRUNCATE model' );
		$this->db->query( $query );

	}

	public function get_model_details( $filter ) {

		$models = $this->db->get_results( "SELECT * FROM model WHERE {$filter}" );

		$styles = array();
		$calls = array();

		foreach ( $models as $model ) {

			// Grab all trim variations per model
			$soap_call = $this->soap_call(
				'describeVehicle',
				array(
					'modelYear' => $model->model_year,
					'modelName' => $model->model_name,
					'makeName' => $model->division_name,
				)
			);

			if ( $soap_call->response->responseStatus->responseCode === 'Unsuccessful' ) {
				var_dump( $soap_call->response );
				break;
			}

			$soap_response = $soap_call->response->style;
			switch ( gettype( $soap_response ) ) {
					// Model only has one trim variation ( object )
				case 'object':
					$soap_call = $this->get_style_details( $soap_response->id );
					$styles[] = $this->set_style( $soap_call, $soap_response->id );
					$calls[] = $soap_call;
					break;
				case 'array':
					// Model has multiple trim variations ( array )
					foreach ( $soap_response as $i => $response_item ) {
						$soap_call = $this->get_style_details( $response_item->id );
						$calls[] = $soap_call;
						$styles[] = $this->set_style( $soap_call, $response_item->id );
						//						break;
					}
					break;
				default:
					var_dump('This should not happen');
			}
		}

		return $styles;
	}

	private function get_style_details( $id ) {
		$soap_response = $this->soap_call(
			'describeVehicle',
			array(
				'styleId' => $id,
				'includeMediaGallery' => 'Both',
				'switch' => array(
					'ShowAvailableEquipment',
					'ShowConsumerInformation',
					'ShowExtendedTechnicalSpecifications',
					'IncludeDefinitions',
				),
			)
		);

		// 396212
		if ( $soap_response->response->responseStatus->responseCode === 'Unsuccessful' ) {
			var_dump( $soap_response->response );
		}
		return $soap_response;
	}

	private function set_option( $item, $child = 'false' ) {
		// Set constant fields and defaults
		$option = array(
			'id'					=> $item->header->id,
			'header'			=> $item->header->_,
			'styleId'			=> $item->styleId,
			'description'	=> addslashes( $item->description ),
			'isChild'			=> $child,
			'oemCode'			=> null,
			'chromeCode'	=> null,
			'msrpMin'			=> null,
			'msrpMax'			=> null,
			'categories'	=> null
		);

		if ( isset( $item->oemCode ) ) {
			$option['oemCode'] = $item->oemCode;
		}
		if ( isset( $item->chromeCode ) ) {
			$option['chromeCode'] = $item->chromeCode;
		}
		if ( isset( $item->price ) ) {
			$option['msrpMin'] = $item->price->msrpMin;
			$option['msrpMax'] = $item->price->msrpMax;
		}
		if ( isset( $item->category ) ) {
			if ( is_object( $item->category ) ) {
				$option['categories'] = [ $item->category->id ];
			} elseif ( is_array( $item->category ) ) {
				foreach ( $item->category as $category ) {
					$option['categories'][] = $category->id;
				}
			}
		}
		$option['categories'] = json_encode( $option['categories'] );
		return $option;
	}

	private function set_style( $soap_call, $style_id ) {

		$style = array();
		$response = $soap_call->response;

		// Store all custom manufacture options
		if ( isset( $response->factoryOption ) ) {
			$style['options'] = array();
			$data = $response->factoryOption;

			foreach( $data as $item ) {
				$option = $this->set_option( $item );
				$style['options'][] = $option;
				// Recursive options
				if ( isset( $item->ambiguousOption ) ) {
					if ( is_object( $item->ambiguousOption ) ) {
						$option = $this->set_option( $item->ambiguousOption, 'true' );
						$style['options'][] = $option;
					} elseif( is_array( $item->ambiguousOption ) ) {
						foreach ( $item->ambiguousOption as $option ) {
							$option = $this->set_option( $option, 'true' );
							$style['options'][] = $option;
						}
					}
				}
			}
		}

		// style properties as defined at the top of the class in an array of objects
		if ( $data = $response->style ) {
			$style['style']  = $this->set_properties( $data, $this->style_properties );
		}

		// ^ engine
		if ( isset( $response->engine ) ) {
			$data = $response->engine;
			if ( is_object( $data ) ) {
				$style['engine'] = $this->set_engine_properties( $data );
			} else if ( is_array( $data ) ) {
				foreach ( $data as $engine ) {
					$style['engine'][] = $this->set_engine_properties( $engine );
				}
			}
		}

		// ^ standard equipment
		if ( isset( $response->standard ) ) {
			$data = $response->standard;
			foreach ( $data as $item ) {

				$style['standard'][] = array(
					'type'				=> $item->header->_,
					'description'	=> $item->description,
					'categories'	=> $this->get_standard_categories( $item )
				);
				// If transmission was not grabbed before, grab from equipment
				if ( ! array_key_exists( 'transmission', $style['style'] ) ) {
					if ( strcasecmp( $item->header->_, 'mechanical' ) === 0 && stripos( $item->description, 'Transmission: ' ) !== false ) {
						$style['style']['transmission'] = str_ireplace( 'transmission: ', '', $item->description );
					}
				}
			}

			// transmission in style from standard equipment
			if ( ! array_key_exists( 'transmission', $style['style'] ) ) {
				$style['style']['transmission'] = null;
			}
		}

		// ^ exterior colors

		if ( isset( $response->exteriorColor ) ) {
			$data = $response->exteriorColor;
			foreach ( $data as $item ) {
				$color = array();
				if ( isset( $item->genericColor ) ) {
					if ( isset( $item->genericColor->name ) ) {
						$color['generic_name'] = $item->genericColor->name;
					}
					if ( isset( $item->genericColor->primary ) ) {
						$color['primary'] = $item->genericColor->primary;
					}
				}
				if ( isset( $item->colorName ) ) {
					$color['name'] = $item->colorName;
				}
				if ( isset( $item->colorCode ) ) {
					$color['code'] = $item->colorCode;
				}

				if ( isset( $item->rgbValue ) ) {
					$color['rgb_value'] = $item->rgbValue;
				}

				if ( isset( $item->styleId ) ) {
					$color['style_id'] = $item->styleId;
				} else {
					$color['style_id'] = $style['style']['style_id'];
				}
				$style['style_colors'][$item->colorCode] = $color;
				$style['style']['exterior_colors'][] = $item->colorName;
			}
		}
		//		var_dump( $style['style_colors'] );
		$style['style']['exterior_colors'] = json_encode( array_unique( $style['style']['exterior_colors'] ) );

		// ^ media gallery
		if ( property_exists( $response->style, 'mediaGallery' ) ) {
			if ( $data = $response->style->mediaGallery->view ) {
				$style_id = $response->style->mediaGallery->styleId;
				foreach ( $data as $image ) {
					if ( property_exists( $image, 'url' ) ) {
						// Only need these images, the rest is grabbed via ftp and optimized on Kraken 
						if ( $image->width == 1280 && $image->height == 960 && $image->backgroundDescription == 'Transparent' ) {
							$image->styleId = $style_id;
							$style['view'][] = $this->set_properties( $image, $this->image_gallery_properties );
						}
					}
				}
			}
		}

		return $style;

	}

	private function get_standard_categories( $item ) {
		$categories = '';
		if ( isset( $item->category ) ) {
			if ( is_array( $item->category ) ) {
				foreach ( $item->category as $category ) {
					$categories .= $category->id . ',';
				}
				$categories = substr( $categories, 0, -1 );
			} else {
				$categories = (string)$item->category->id;
			}
		}
		return $categories;
	}

	private function set_properties( $style, $properties ) {

		$returned_properties = array();
		// Loop through all properties we need for this particular part of the api call
		foreach ( $properties as $property ) {
			// Check if a particular property exists in the chrome object returned that we want
			if ( property_exists( $style, $property['property'] ) ) {
				// Assign value of the property to $property_value
				$property_value = $style->{$property['property']};
				$value = array();
				switch ( gettype( $property_value ) ) {
						// If property value is an object
					case 'object':
						// if the value attribute exists for the property array, use this to get the value needed
						if ( array_key_exists( 'value', $property ) ) {
							$value = $this->set_value( $property_value, $property['value'] );
						} else {
							$value = $property_value;
						}
						break;
						// If property is an array
					case 'array':
						// Loop through each object in the array and assign it the value in the value attribute
						foreach ( $property_value as $single_property ) {
							if ( array_key_exists( 'value', $property ) ) {
								$value[] = $this->set_value( $single_property, $property['value'] );
							} else {
								$value[] = $single_property;
							}
						}
						break;
						// In all other cases, you just want to assign it the value the api call gives us
					case 'string':
					default:
						$value = $property_value;
				}
				if ( is_array( $value ) || is_object( $value ) ) {
					$value = json_encode( $value );
				}
				$returned_properties[ $property['field'] ] = $value;
				// if property doesn't exist
			} else {
				$returned_properties[ $property['field'] ] = null;
			}
		}

		return $returned_properties;

	}

	private function set_value( $object, $property ) {

		$value = array();

		switch ( gettype( $property ) ) {
			case 'array':
				foreach ( $property as $item ) {
					if ( property_exists( $object, $item ) ) {
						$value[] = $object->{$item};
					} else {
						$value = null;
					}
				}
				break;
			default:
				$value = $object->{$property};
		}

		return $value;

	}

	private function set_engine_properties( $data ) {

		$engine = new stdClass;

		if ( isset( $data->engineType ) && isset( $data->engineType->_ ) ) {
			$engine->engine_type = $data->engineType->_;
		} else {
			$engine->engine_type = 'null';
		}

		if ( isset( $data->fuelType ) && isset( $data->fuelType->_ ) ) {
			$engine->fuel_type = $data->fuelType->_;
		} else {
			$engine->fuel_type = 'null';
		}

		if ( isset( $data->cylinders ) ) {
			$engine->cylinders = $data->cylinders;
		} else {
			$engine->cylinders = 'null';
		}

		if ( isset( $data->fuelEconomy ) ) {
			$this->set_fuel_economy( $engine, $data->fuelEconomy );
		}

		if ( isset( $data->fuelCapacity ) ) {
			if ( isset( $data->fuelCapacity->low ) ) {
				$engine->fuel_capacity_low = $data->fuelCapacity->low;
			} else {
				$engine->fuel_capacity_low = 'null';
			}
			if ( isset( $data->fuelCapacity->high ) ) {
				$engine->fuel_capacity_high = $data->fuelCapacity->high;
			} else {
				$engine->fuel_capacity_high = 'null';
			}
			if ( isset( $data->fuelCapacity->unit ) ) {
				$engine->fuel_capacity_unit = $data->fuelCapacity->unit;
			} else {
				$engine->fuel_capacity_unit = 'null';
			}
		}

		if ( isset( $data->horsepower->value ) ) {
			$engine->horsepower = $data->horsepower->value;
		} else {
			$engine->horsepower = 'null';
		}

		if ( isset( $data->horsepower->rpm ) ) {
			$engine->horsepower_rpm = $data->horsepower->rpm;
		} else {
			$engine->horsepower_rpm = 'null';
		}

		if ( isset( $data->netTorque->value ) ) {
			$engine->net_torque = $data->netTorque->value;
		} else {
			$engine->net_torque = 'null';
		}

		if ( isset( $data->netTorque->rpm ) ) {
			$engine->net_torque_rpm = $data->netTorque->rpm;
		} else {
			$engine->net_torque_rpm = 'null';
		}

		if ( isset( $data->displacement->value ) ) {
			if ( isset( $data->displacement->value->_ ) ) {
				$engine->displacement = $data->displacement->value->_;
				$engine->engine = $data->displacement->value->_;
			} else {
				$engine->displacement = 'null';
			}
			if ( isset( $data->displacement->value->unit ) ) {
				$engine->displacement_unit = $data->displacement->value->unit;
				if ( $data->displacement->value->unit === 'liters' ) {
					$engine->engine .= 'L';
				}
			} else {
				$engine->displacement_unit = 'null';
			}
		}

		if ( isset( $data->engineType ) ) {
			$engine_type = str_ireplace( ' Cylinder Engine', '', $data->engineType->_ );
			$engine->engine .= ' ' . $engine_type;
		} else {
			$engine->engine = 'null';
		}

		return $engine;

	}

	private function set_fuel_economy( &$engine, $data ) {

		if ( isset( $data->unit ) ) {

			switch ( $data->unit ) {
				case 'L/100 km':
					$engine->fuel_economy_city_low = ( isset( $data->city->low ) ) ? $data->city->low : 'null';
					$engine->fuel_economy_hwy_low = ( isset( $data->hwy->low ) ) ? $data->hwy->low : 'null';
					$engine->fuel_economy_city_high = ( isset( $data->city->high ) ) ? $data->city->high : 'null';
					$engine->fuel_economy_hwy_high = ( isset( $data->hwy->high ) ) ? $data->hwy->high : 'null';
					break;
				case 'MPG':
					$engine->fuel_economy_city_low = ( isset( $data->city->low ) ) ? round( ( 100 * 4.54609 ) / ( 1.609344 * $data->city->low ), 1 ) : 'null';
					$engine->fuel_economy_hwy_low = ( isset( $data->hwy->low ) ) ? round( ( 100 * 4.54609 ) / ( 1.609344 * $data->hwy->low ), 1 ) : 'null';
					$engine->fuel_economy_city_high = ( isset( $data->city->high ) ) ? round( ( 100 * 4.54609 ) / ( 1.609344 * $data->city->high ), 1 ) : 'null';
					$engine->fuel_economy_hwy_high = ( isset( $data->hwy->high ) ) ? round( ( 100 * 4.54609 ) / ( 1.609344 * $data->hwy->high ), 1 ) : 'null';
					break;
				default:
					$engine->fuel_economy_city_low = 'null';
					$engine->fuel_economy_hwy_low = 'null';
					$engine->fuel_economy_city_high = 'null';
					$engine->fuel_economy_hwy_high = 'null';
			}
		}
	}

	public function update_styles( $styles ) {

		$style_query_sql_values = array();
		$engine_query_sql_values = array();

		foreach ( $styles as $style ) {

			if ( array_key_exists( 'style', $style ) ) {

				$value = $style['style'];
				$style_id = $value['style_id'];

				$exists = $this->db->get_row( "SELECT * FROM style WHERE style_id = {$value['style_id']} AND acode = '{$value['acode']}'" );
				if ( $exists !== null ) {
					$this->db->delete( 'style', array( 'style_id' => $value['style_id'] ) );
					$this->db->delete( 'media', array( 'style_id' => $value['style_id'] ) );
					$this->db->delete( 'engine', array( 'style_id' => $value['style_id'] ) );
					$this->db->delete( 'standard', array( 'style_id' => $value['style_id'] ) );
					$this->db->delete( 'exterior_color', array( 'style_id' => $value['style_id'] ) );
					$this->db->delete( 'option', array( 'style_id' => $value['style_id'] ) );
				}

				$style_query = 'INSERT style ( style_id, model_code, model_year, model_name, division, subdivision, trim, body_type, market_class, msrp, drivetrain, transmission, doors, acode, exterior_colors, last_updated ) VALUES ';
				$style_query_sql_values[] = "({$value['style_id']}, '{$value['model_code']}', {$value['model_year']}, '{$value['model_name']}', '{$value['division']}', '{$value['subdivision']}', '{$value['trim']}', '{$value['body_type']}', '{$value['market_class']}', {$value['msrp']}, '{$value['drivetrain']}', '{$value['transmission']}', {$value['doors']}, '{$value['acode']}', '{$value['exterior_colors']}', now())";

				$value = $style['engine'];

				$engine_query = 'INSERT engine ( style_id, engine, engine_type, fuel_type, cylinders, fuel_capacity_high, fuel_capacity_low, fuel_capacity_unit, fuel_economy_hwy_high, fuel_economy_hwy_low, fuel_economy_city_high, fuel_economy_city_low, horsepower, horsepower_rpm, net_torque, net_torque_rpm, displacement, displacement_unit ) VALUES ';

				if ( is_object( $value ) ) {
					$engine_query_sql_values[] = "({$style_id}, '{$value->engine}', '{$value->engine_type}', '{$value->fuel_type}', {$value->cylinders}, {$value->fuel_capacity_high}, {$value->fuel_capacity_low}, '{$value->fuel_capacity_unit}', {$value->fuel_economy_hwy_high}, {$value->fuel_economy_hwy_low}, {$value->fuel_economy_city_high}, {$value->fuel_economy_city_low}, {$value->horsepower}, {$value->horsepower_rpm}, {$value->net_torque}, {$value->net_torque_rpm}, {$value->displacement}, '{$value->displacement_unit}')";
				} else if ( is_array( $value ) ) {
					foreach ( $value as $single_value ) {
						$engine_query_sql_values[] = "({$style_id}, '{$single_value->engine}', '{$single_value->engine_type}', '{$single_value->fuel_type}', {$single_value->cylinders}, {$single_value->fuel_capacity_high}, {$single_value->fuel_capacity_low}, '{$single_value->fuel_capacity_unit}', {$single_value->fuel_economy_hwy_high}, {$single_value->fuel_economy_hwy_low}, {$single_value->fuel_economy_city_high}, {$single_value->fuel_economy_city_low}, {$single_value->horsepower}, {$single_value->horsepower_rpm}, {$single_value->net_torque}, {$single_value->net_torque_rpm}, {$single_value->displacement}, '{$single_value->displacement_unit}')";
					}
				}
			}

			if ( array_key_exists('style_colors', $style ) ) {
				$colors = $style['style_colors'];
				$color_query = 'INSERT exterior_color( style_id, generic_name, name, code, rgb_value ) VALUES ';
				foreach ( $colors as $color ) {
					$color_query_sql_values = "({$color['style_id']}, '{$color['generic_name']}', '{$color['name']}', '{$color['code']}', '{$color['rgb_value']}')";
					$this->db->query( $color_query . $color_query_sql_values );
				}
			}

			if ( array_key_exists( 'options', $style ) ) {
				$options = $style['options'];
				$option_query = 'INSERT option( option_id, header, style_id, description, is_child, oem_code, chrome_code, msrp_min, msrp_max, categories ) VALUES ';
				foreach ( $options as $option ) {
					$option_query_sql_values = "({$option['id']}, '{$option['header']}', {$option['styleId']}, '{$option['description']}', '{$option['isChild']}', '{$option['oemCode']}', '{$option['chromeCode']}', {$option['msrpMin']}, {$option['msrpMax']}, '{$option['categories']}' )";

					$this->db->query( $option_query . $option_query_sql_values );
				}
			}
			
			
			// Adjust this
			if ( array_key_exists( 'view', $style ) ) {
				$media_query = 'INSERT media ( style_id, type, url, width, height, shot_code, background, created ) VALUES ';
				foreach ( $style['view'] as $image ) {
					$media_query_sql_values[] = "('{$image['style_id']}', 'view', '{$image['url']}', {$image['width']}, {$image['height']}, {$image['shot_code']}, '{$image['background_description']}', now())";
				}
			}

			if ( array_key_exists( 'standard', $style ) ) {
				$standard_query = 'INSERT standard ( style_id, type, description, categories ) VALUES ';
				foreach ( $style['standard'] as $item ) {
					$standard_query_sql_values[] = "({$style_id}, '{$item['type']}', '{$item['description']}', '{$item['categories']}')";
				}
			}
		}

		$style_query .= implode( ',', $style_query_sql_values );
		$this->db->query( $style_query );

		$engine_query .= implode( ',', $engine_query_sql_values );
		$this->db->query( $engine_query );

		$media_query .= implode( ',', $media_query_sql_values );
		$this->db->query( $media_query );

		//		$colorized_media_query .= implode( ',', $colorized_media_query_sql_values );
		//		$this->db->query( $colorized_media_query );

		$standard_query .= implode( ',', $standard_query_sql_values );
		$this->db->query( $standard_query );

	}

	private function define_equipment_group( $equipment_group_raw ) {

		if ( check_string_for( $equipment_group_raw, array( 'mechanical', 'chassis', 'window', 'mirrors' ) ) ) {
			$equipment_group = 'Performance';
		} elseif ( check_string_for( $equipment_group_raw, array( 'exterior' ) ) ) {
			$equipment_group = 'Appearance';
		} elseif ( check_string_for( $equipment_group_raw, array( 'entertainment', 'audio' ) ) ) {
			$equipment_group = 'Entertainment';
		} elseif ( check_string_for( $equipment_group_raw, array( 'interior', 'convenience', 'air', 'floor mats', 'locks', 'seating' ) ) ) {
			$equipment_group = 'Comfort';
		} elseif ( check_string_for( $equipment_group_raw, array( 'safety' ) ) ) {
			$equipment_group = 'Safety';
		} elseif ( check_string_for( $equipment_group_raw, array( 'engine', 'powertrain', 'transmission' ) ) ) {
			$equipment_group = 'Engine';
		} elseif ( check_string_for( $equipment_group_raw, array( 'accessories' ) ) ) {
			$equipment_group = 'Accessories';
		} elseif ( check_string_for( $equipment_group_raw, array( 'warranty' ) ) ) {
			$equipment_group = 'Warranty';
		} else {
			$equipment_group = 'Other';
		}
		$equipment_details_section = 0;
		return array(
			'equipment_group' => $equipment_group,
			'equipment_details_section' => $equipment_details_section,
		);

	}

	//	Function:	Checks an array for the existence of any of the needles
	private function check_string_for( $haystack, $needles ) {
		foreach ( $needles as $needle ) {
			if ( stripos( $haystack, $needle ) !== false ) {
				return true;
			}
		}
		return false;
	}


	private function find_engine_type( $body_text ) {

		if ( check_string_for( $body_text, array( 'i-', 'straight', 'i3', 'i4', 'i5', 'i6', 'i7', 'i8' ) ) ) {
			$engine_type = 'I';
		} elseif ( check_string_for( $body_text, array( 'v6', 'v8', 'v10', 'v12', 'v16', 'v-' ) ) ) {
			$engine_type = 'V';
		} elseif ( check_string_for( $body_text, array( 'boxer', 'h-', 'h4', 'h6' ) ) ) {
			$engine_type = 'H';
		} elseif ( check_string_for( $body_text, array( 'rotary' ) ) ) {
			$engine_type = 'Rotary';
		}

		return $engine_type;
	}

	private function find_drive_train( $body_text ) {

		if ( check_string_for( $body_text, array( 'rear', 'rwd' ) ) ) {
			$drive_train = 'RWD';
		} elseif ( check_string_for( $body_text, array( 'front', 'fwd' ) ) ) {
			$drive_train = 'FWD';
		} elseif ( check_string_for( $body_text, array( 'all', 'awd' ) ) ) {
			$drive_train = 'AWD';
		} elseif ( check_string_for( $body_text, array( '4', 'four', '4wd' ) ) ) {
			$drive_train = '4WD';
		}

		return $drive_train;
	}

	private function transmission_vehicle_filter( $text, $special = '' ) {
		if ( check_string_for( $text, array( 'automatic', 'A4', 'A5', 'A6', 'A7', 'A8' ) ) ) {
			$text = 'Automatic';
		} elseif ( check_string_for( $text, array( 'manual', 'M4', 'M5', 'M6', 'M7', 'M8' ) ) ) {
			$text = 'Manual';
		}
		return $text;
	}

	public function truncate_all( $tables ) {
		foreach ( $tables as $table ) {
			$this->db->query('TRUNCATE ' . $table );
		}
	}
}


//$response = $obj->soap_call(
//	'describeVehicle',
//	array(
//		'styleId' => 390597,
//		'includeMediaGallery' => 'Both',
//		'switch' => array(
//			'ShowAvailableEquipment',
//			'ShowConsumerInformation',
//			'ShowExtendedTechnicalSpecifications',
//			'IncludeDefinitions',
//		),
//	)
//);
?>