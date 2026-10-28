<?php
/**
 * Elementor dynamic tags van Scrolla Core (groep "Scrolla").
 * Wordt alleen geladen vanuit de hook elementor/dynamic_tags/register.
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Core\DynamicTags\Tag;
use Elementor\Core\DynamicTags\Data_Tag;
use Elementor\Modules\DynamicTags\Module as TagsModule;

if ( ! class_exists( 'Scrolla_Tag_Algemeen_Tekst' ) ) {

	class Scrolla_Tag_Algemeen_Tekst extends Tag {
		public function get_name() { return 'scrolla-algemeen-tekst'; }
		public function get_title() { return 'Algemeen (tekst)'; }
		public function get_group() { return 'scrolla'; }
		public function get_categories() { return [ TagsModule::TEXT_CATEGORY ]; }
		protected function register_controls() {
			$opts = [];
			foreach ( Scrolla_Algemeen::fields() as $k => $f ) {
				$opts[ $k ] = $f[0];
			}
			$this->add_control( 'veld', [ 'label' => 'Veld', 'type' => Controls_Manager::SELECT, 'options' => $opts, 'default' => 'telefoon' ] );
		}
		public function render() {
			$k = $this->get_settings( 'veld' );
			$v = Scrolla_Algemeen::value( (string) $k );
			echo 'openingstijden' === $k ? nl2br( esc_html( $v ) ) : esc_html( $v ); // phpcs:ignore
		}
	}

	class Scrolla_Tag_Algemeen_Link extends Data_Tag {
		public function get_name() { return 'scrolla-algemeen-link'; }
		public function get_title() { return 'Algemeen (link)'; }
		public function get_group() { return 'scrolla'; }
		public function get_categories() { return [ TagsModule::URL_CATEGORY ]; }
		protected function register_controls() {
			$opts = [];
			foreach ( Scrolla_Algemeen::fields() as $k => $f ) {
				if ( in_array( $f[1], [ 'url', 'tel', 'email' ], true ) ) {
					$opts[ $k ] = $f[0];
				}
			}
			$this->add_control( 'veld', [ 'label' => 'Veld', 'type' => Controls_Manager::SELECT, 'options' => $opts, 'default' => 'telefoon' ] );
		}
		public function get_value( array $options = [] ) {
			return Scrolla_Algemeen::value( (string) $this->get_settings( 'veld' ), true );
		}
	}
}

if ( class_exists( 'Scrolla_Types' ) && ! class_exists( 'Scrolla_Tag_Veld_Tekst' ) ) {

	trait Scrolla_Veld_Controls {
		protected function veld_opties( array $types ): array {
			$opts = [];
			foreach ( Scrolla_Types::all_fields() as $key => $f ) {
				if ( in_array( $f['type'], $types, true ) ) {
					$opts[ $key ] = $f['label'] . ' (' . $f['post_type_label'] . ')';
				}
			}
			return $opts;
		}
		protected function veld_waarde(): string {
			return (string) get_post_meta( get_the_ID(), (string) $this->get_settings( 'veld' ), true );
		}
	}

	class Scrolla_Tag_Veld_Tekst extends Tag {
		use Scrolla_Veld_Controls;
		public function get_name() { return 'scrolla-veld-tekst'; }
		public function get_title() { return 'Veld (tekst)'; }
		public function get_group() { return 'scrolla'; }
		public function get_categories() { return [ TagsModule::TEXT_CATEGORY, TagsModule::NUMBER_CATEGORY ]; }
		protected function register_controls() {
			$this->add_control( 'veld', [ 'label' => 'Veld', 'type' => Controls_Manager::SELECT, 'options' => $this->veld_opties( [ 'text', 'textarea', 'number', 'date', 'url', 'bool' ] ) ] );
		}
		public function render() {
			$k = (string) $this->get_settings( 'veld' );
			$f = Scrolla_Types::all_fields()[ $k ] ?? null;
			$v = $this->veld_waarde();
			if ( $f && 'date' === $f['type'] && $v ) {
				$v = date_i18n( get_option( 'date_format' ), strtotime( $v ) );
			} elseif ( $f && 'bool' === $f['type'] ) {
				$v = $v ? 'Ja' : 'Nee';
			}
			echo $f && 'textarea' === $f['type'] ? nl2br( esc_html( $v ) ) : esc_html( $v ); // phpcs:ignore
		}
	}

	class Scrolla_Tag_Veld_Link extends Data_Tag {
		use Scrolla_Veld_Controls;
		public function get_name() { return 'scrolla-veld-link'; }
		public function get_title() { return 'Veld (link)'; }
		public function get_group() { return 'scrolla'; }
		public function get_categories() { return [ TagsModule::URL_CATEGORY ]; }
		protected function register_controls() {
			$this->add_control( 'veld', [ 'label' => 'Veld', 'type' => Controls_Manager::SELECT, 'options' => $this->veld_opties( [ 'url' ] ) ] );
		}
		public function get_value( array $options = [] ) { return $this->veld_waarde(); }
	}

	class Scrolla_Tag_Veld_Afbeelding extends Data_Tag {
		use Scrolla_Veld_Controls;
		public function get_name() { return 'scrolla-veld-afbeelding'; }
		public function get_title() { return 'Veld (afbeelding)'; }
		public function get_group() { return 'scrolla'; }
		public function get_categories() { return [ TagsModule::IMAGE_CATEGORY ]; }
		protected function register_controls() {
			$this->add_control( 'veld', [ 'label' => 'Veld', 'type' => Controls_Manager::SELECT, 'options' => $this->veld_opties( [ 'image' ] ) ] );
		}
		public function get_value( array $options = [] ) {
			$id = (int) $this->veld_waarde();
			return $id ? [ 'id' => $id, 'url' => (string) wp_get_attachment_image_url( $id, 'full' ) ] : [];
		}
	}
}
