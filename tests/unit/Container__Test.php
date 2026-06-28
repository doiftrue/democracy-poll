<?php

namespace DemocracyPoll;

use DemocracyPoll\Infra\Container;
use InvalidArgumentException;
use RuntimeException;

class Container_Test_Dependency {}

class Container_Test_Service {

	public Container_Test_Dependency $dependency;
	public string $value;

	public function __construct( Container_Test_Dependency $dependency, string $value = 'default value' ) {
		$this->dependency = $dependency;
		$this->value = $value;
	}

}

class Container_Test_Service_Double extends Container_Test_Service {}

class Container_Test_Required_Scalar {

	public string $value;

	public function __construct( string $value ) {
		$this->value = $value;
	}

}

class Container_Test_Container_Aware {

	public Container $container;

	public function __construct( Container $container ) {
		$this->container = $container;
	}

}

class Container__Test extends DemocTestCase {

	/**
	 * @covers Container::__construct()
	 * @covers Container::get()
	 * @covers Container::has()
	 */
	public function test__container_makes_itself_available_as_a_service(): void {
		$container = new Container();

		$this->assertTrue( $container->has( Container::class ) );
		$this->assertSame( $container, $container->get( Container::class ) );
	}

	/**
	 * @covers Container::has()
	 * @covers Container::get()
	 * @covers Container::set()
	 */
	public function test__has_recognizes_registered_and_cached_services(): void {
		$container = new Container();

		$this->assertFalse( $container->has( Container_Test_Dependency::class ) );

		$container->get( Container_Test_Dependency::class );

		$this->assertTrue( $container->has( Container_Test_Dependency::class ) );

		$container->set( Container_Test_Service::class, new Container_Test_Service( new Container_Test_Dependency() ) );

		$this->assertTrue( $container->has( Container_Test_Service::class ) );
	}

	/**
	 * @dataProvider data__invalid_service_definitions
	 * @covers Container::set()
	 *
	 * @param mixed $definition
	 */
	public function test__set_rejects_invalid_service_definition( $definition ): void {
		$container = new Container();

		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Service definition `invalid` must be an object or class name.' );

		$container->set( 'invalid', $definition );
	}

	public function data__invalid_service_definitions(): array {
		return [
			'null'    => [ null ],
			'boolean' => [ true ],
			'integer' => [ 10 ],
			'array'   => [ [] ],
		];
	}

	/**
	 * @covers Container::set()
	 * @covers Container::get()
	 */
	public function test__set_replaces_registered_service_and_clears_its_cache(): void {
		$container = new Container();
		$first = new Container_Test_Dependency();
		$replacement = new Container_Test_Dependency();

		$container->set( Container_Test_Dependency::class, $first );
		$this->assertSame( $first, $container->get( Container_Test_Dependency::class ) );

		$container->set( Container_Test_Dependency::class, $replacement );

		$this->assertSame( $replacement, $container->get( Container_Test_Dependency::class ) );
	}

	/**
	 * @covers Container::get()
	 */
	public function test__get_autowires_dependencies_and_caches_created_service(): void {
		$container = new Container();

		$service = $container->get( Container_Test_Service::class );

		$this->assertInstanceOf( Container_Test_Dependency::class, $service->dependency );
		$this->assertSame( 'default value', $service->value );
		$this->assertSame( $service, $container->get( Container_Test_Service::class ) );
		$this->assertSame( $service->dependency, $container->get( Container_Test_Dependency::class ) );
	}

	/**
	 * @covers Container::get()
	 * @covers Container::set()
	 */
	public function test__get_creates_service_from_registered_class_name(): void {
		$container = new Container();
		$container->set( Container_Test_Service::class, Container_Test_Service_Double::class );

		$service = $container->get( Container_Test_Service::class );

		$this->assertInstanceOf( Container_Test_Service_Double::class, $service );
		$this->assertSame( $service, $container->get( Container_Test_Service::class ) );
	}

	/**
	 * @covers Container::get()
	 * @covers Container::set()
	 */
	public function test__get_passes_container_to_registered_factory_and_caches_created_service(): void {
		$container = new Container();
		$container->set(
			Container_Test_Container_Aware::class,
			static fn( Container $factory_container ) => new Container_Test_Container_Aware( $factory_container )
		);

		$service = $container->get( Container_Test_Container_Aware::class );

		$this->assertSame( $container, $service->container );
		$this->assertSame( $service, $container->get( Container_Test_Container_Aware::class ) );
	}

	/**
	 * @covers Container::get()
	 * @covers Container::set()
	 */
	public function test__get_rejects_non_object_created_by_factory(): void {
		$container = new Container();
		$container->set( Container_Test_Service::class, static fn( Container $container ) => 'not an object' );

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage(
			'Factory for service `DemocracyPoll\\Container_Test_Service` must return an object.'
		);

		$container->get( Container_Test_Service::class );
	}

	/**
	 * @covers Container::get()
	 */
	public function test__get_reports_unknown_service(): void {
		$container = new Container();

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'Service `unknown-service` not found in the Container.' );

		$container->get( 'unknown-service' );
	}

	/**
	 * @covers Container::get()
	 */
	public function test__get_reports_required_scalar_constructor_argument(): void {
		$container = new Container();

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage(
			'Parameter `value` of `DemocracyPoll\\Container_Test_Required_Scalar` not resolved.'
		);

		$container->get( Container_Test_Required_Scalar::class );
	}

	/**
	 * @covers Container::make()
	 */
	public function test__make_uses_runtime_parameters_and_autowires_dependencies(): void {
		$container = new Container();

		$service = $container->make( Container_Test_Service::class, [ 'value' => 'runtime value' ] );

		$this->assertInstanceOf( Container_Test_Dependency::class, $service->dependency );
		$this->assertSame( 'runtime value', $service->value );
	}

	/**
	 * @covers Container::make()
	 * @covers Container::has()
	 */
	public function test__make_does_not_cache_created_service(): void {
		$container = new Container();

		$first = $container->make( Container_Test_Service::class );
		$second = $container->make( Container_Test_Service::class );

		$this->assertNotSame( $first, $second );
		$this->assertSame( $first->dependency, $second->dependency );
		$this->assertSame( 'default value', $first->value );
		$this->assertFalse( $container->has( Container_Test_Service::class ) );
	}

	/**
	 * @covers Container::make()
	 * @covers Container::set()
	 */
	public function test__make_uses_registered_object_as_is(): void {
		$container = new Container();
		$registered = new Container_Test_Service( new Container_Test_Dependency() );
		$container->set( Container_Test_Service::class, $registered );

		$this->assertSame( $registered, $container->make( Container_Test_Service::class ) );
	}

	/**
	 * @covers Container::make()
	 * @covers Container::set()
	 */
	public function test__make_uses_registered_class_name(): void {
		$container = new Container();
		$container->set( Container_Test_Service::class, Container_Test_Service_Double::class );

		$service = $container->make( Container_Test_Service::class, [ 'value' => 'runtime value' ] );

		$this->assertInstanceOf( Container_Test_Service_Double::class, $service );
		$this->assertSame( 'runtime value', $service->value );
	}

	/**
	 * @covers Container::make()
	 * @covers Container::set()
	 */
	public function test__make_autowires_factory_dependencies_and_uses_runtime_parameters(): void {
		$container = new Container();
		$container->set(
			Container_Test_Service::class,
			static fn( Container_Test_Dependency $dependency, string $value ) =>
				new Container_Test_Service( $dependency, $value )
		);

		$service = $container->make( Container_Test_Service::class, [ 'value' => 'runtime value' ] );

		$this->assertInstanceOf( Container_Test_Dependency::class, $service->dependency );
		$this->assertSame( 'runtime value', $service->value );
	}

	/**
	 * @covers Container::make()
	 */
	public function test__make_runtime_parameters_override_autowired_dependencies(): void {
		$container = new Container();
		$dependency = new Container_Test_Dependency();

		$service = $container->make(
			Container_Test_Service::class,
			[ 'dependency' => $dependency, 'value' => 'runtime value' ]
		);

		$this->assertSame( $dependency, $service->dependency );
	}

	/**
	 * @covers Container::make()
	 * @covers Container::set()
	 */
	public function test__make_rejects_non_object_created_by_factory(): void {
		$container = new Container();
		$container->set( Container_Test_Service::class, static fn() => 'not an object' );

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage(
			'Factory for service `DemocracyPoll\\Container_Test_Service` must return an object.'
		);

		$container->make( Container_Test_Service::class );
	}

	/**
	 * @covers Container::make()
	 * @covers Container::set()
	 */
	public function test__make_reports_unresolved_factory_parameter(): void {
		$container = new Container();
		$container->set(
			Container_Test_Service::class,
			static fn( string $value ) => new Container_Test_Service( new Container_Test_Dependency(), $value )
		);

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'Parameter `value` of `DemocracyPoll\\Container__Test` not resolved.' );

		$container->make( Container_Test_Service::class );
	}

	/**
	 * @covers Container::make()
	 */
	public function test__make_reports_unknown_service(): void {
		$container = new Container();

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'Definition `unknown-service` could not be resolved because class not exist.' );

		$container->make( 'unknown-service' );
	}

}
