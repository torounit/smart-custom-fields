<?php
/**
 * SCF
 * Version    : 1.0.0
 * Author     : Takashi Kitajima
 * Created    : September 23, 2014
 * Modified   :
 * License    : GPLv2
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */
class SCF {

	/**
	 * データ取得処理は重いので、一度取得したデータは cache に保存する。
	 * キーに post_id を設定すること。
	 */
	protected static $cache = array();

	/**
	 * データ取得処理は重いので、一度取得した設定データは settings_posts_cache に保存する。
	 * キーに post_type を設定すること。
	 */
	protected static $settings_posts_cache = array();

	/**
	 * データ取得処理は重いので、一度取得した設定データは cache に保存する。
	 * キーに post_type を設定すること。
	 */
	protected static $settings_cache = array();

	/**
	 * データ取得処理は重いので、一度取得した設定データは cache に保存する。
	 * キーに post_id を設定すること。
	 */
	protected static $repeat_multiple_data_cache = array();

	/**
	 * gets
	 * その投稿の全てのメタデータを良い感じに取得
	 * @param $post_id
	 * @return array
	 */
	public static function gets( $post_id = null ) {
		if ( is_null( $post_id ) ) {
			$post_id = get_the_ID();
		}
		$post_id = self::get_real_post_id( $post_id );

		$repeat_multiple_data = self::get_repeat_multiple_data( $post_id );

		// 設定画面で未設定のメタデータは投稿が保持していても出力しないようにしないといけないので
		// 設定データを取得して出力して良いか判別する
		$post_type = get_post_type();
		$settings = self::get_settings( $post_type );

		$return_post_meta = array();
		foreach ( $settings as $setting ) {
			foreach ( $setting as $group ) {
				// グループ名と一致する場合はそのグループ内のフィールドを配列で返す
				$is_repeat = ( isset( $group['repeat'] ) && $group['repeat'] === true ) ? true : false;
				if ( $is_repeat && !empty( $group['group-name'] ) ) {
					$return_post_meta[$group['group-name']] = self::get_sub_field( $post_id, $group['group-name'], $group['fields'] );
				}
				// グループ名と一致しない場合は一致するフィールドを返す
				else {
					foreach ( $group['fields'] as $field ) {
						$return_post_meta[$field['name']] = $post_meta = self::get_field( $post_id, $field, $is_repeat );
					}
				}
			}
		}
		return $return_post_meta;
	}

	/**
	 * get
	 * その投稿の任意のメタデータを良い感じに取得
	 * @param string $name グループ名もしくはフィールド名
	 * @param int $post_id
	 * @return mixed
	 */
	public static function get( $name, $post_id = null ) {
		if ( is_null( $post_id ) ) {
			$post_id = get_the_ID();
		}

		$post_id = self::get_real_post_id( $post_id );

		if ( self::get_cache( $post_id, $name ) ) {
			return self::get_cache( $post_id, $name );
		}

		$repeat_multiple_data = self::get_repeat_multiple_data( $post_id );

		// 設定画面で未設定のメタデータは投稿が保持していても出力しないようにしないといけないので
		// 設定データを取得して出力して良いか判別する
		$post_type = get_post_type();
		$settings = self::get_settings( $post_type );

		foreach ( $settings as $setting ) {
			foreach ( $setting as $group ) {
				// グループ名と一致する場合はそのグループ内のフィールドを配列で返す
				$is_repeat = ( isset( $group['repeat'] ) && $group['repeat'] === true ) ? true : false;
				if ( $is_repeat && !empty( $group['group-name'] ) && $group['group-name'] === $name ) {
					return self::get_sub_field( $post_id, $name, $group['fields'] );
				}
				// グループ名と一致しない場合は一致するフィールドを返す
				else {
					foreach ( $group['fields'] as $field ) {
						if ( $field['name'] !== $name ) {
							continue;
						}
						$post_meta = self::get_field( $post_id, $field, $is_repeat );
						if ( !is_null( $post_meta ) ) {
							return $post_meta;
						}
					}
				}
			}
		}
	}

	protected static function get_real_post_id( $post_id ) {
		if ( is_preview() ) {
			$preview_post = wp_get_post_autosave( $post_id );
			if ( isset( $preview_post->ID ) ) {
				$post_id = $preview_post->ID;
			}
		}
		return $post_id;
	}

	/**
	 * save_cache
	 * @param int $post_id
	 * @param string $name
	 * @param mixed $data
	 */
	protected static function save_cache( $post_id, $name, $data ) {
		self::$cache[$post_id][$name] = $data;
	}

	/**
	 * get_cache
	 * @param int $post_id
	 * @param string $name
	 * @return mixed
	 */
	protected static function get_cache( $post_id, $name = null ) {
		if ( is_null( $name ) ) {
			if ( isset( self::$cache[$post_id] ) ) {
				return self::$cache[$post_id];
			}
		} else {
			if ( isset( self::$cache[$post_id][$name] ) ) {
				return self::$cache[$post_id][$name];
			}
		}
	}

	/**
	 * get_sub_field
	 * @param int $post_id
	 * @param string $group_name
	 * @param array $fields
	 * @return mixed $post_meta
	 */
	protected static function get_sub_field( $post_id, $group_name, $fields ) {
		$post_meta = array();
		foreach ( $fields as $field ) {
			$_post_meta = get_post_meta( $post_id, $field['name'] );
			// チェックボックスの場合
			$repeat_multiple_data = self::get_repeat_multiple_data( $post_id );
			if ( is_array( $repeat_multiple_data ) && array_key_exists( $field['name'], $repeat_multiple_data ) ) {
				$start = 0;
				foreach ( $repeat_multiple_data[$field['name']] as $repeat_checkbox_key => $repeat_checkbox_value ) {
					if ( $repeat_checkbox_value === 0 ) {
						$value = array();
					} else {
						$value = array_slice( $_post_meta, $start, $repeat_checkbox_value );
						$start = $repeat_checkbox_value;
					}
					$post_meta[$repeat_checkbox_key][$field['name']] = $value;
				}
			}
			// チェックボックス以外
			else {
				foreach ( $_post_meta as $_post_meta_key => $value ) {
					if ( in_array( $field['type'], array( 'textarea', 'wysiwyg' ) ) ) {
						$value = apply_filters( 'the_content', $value );
					} elseif ( $field['type'] === 'relation' ) {
						if ( get_post_status( $value ) !== 'publish' )
							continue;
					}
					$post_meta[$_post_meta_key][$field['name']] = $value;
				}
			}
		}
		self::save_cache( $post_id, $group_name, $post_meta );
		return $post_meta;
	}

	/**
	 * get_field
	 * @param int $post_id
	 * @param array $field
	 * @param bool $is_repeat
	 * @return mixed $post_meta
	 */
	protected static function get_field( $post_id, $field, $is_repeat, $name = null ) {
		if ( in_array( $field['type'], array( 'check', 'relation' ) ) || $is_repeat ) {
			$post_meta = get_post_meta( $post_id, $field['name'] );
		} else {
			$post_meta = get_post_meta( $post_id, $field['name'], true );
		}
		if ( in_array( $field['type'], array( 'textarea', 'wysiwyg' ) ) ) {
			if ( is_array( $post_meta ) ) {
				$_post_meta = array();
				foreach ( $post_meta as $key => $value ) {
					$_post_meta[$key] = apply_filters( 'the_content', $value );
				}
				$post_meta = $_post_meta;
			} else {
				$post_meta = apply_filters( 'the_content', $post_meta );
			}
		} elseif ( $field['type'] === 'relation' ) {
			$_post_meta = array();
			foreach ( $post_meta as $post_id ) {
				if ( get_post_status( $post_id ) !== 'publish' )
					continue;
				$_post_meta[] = $post_id;
			}
			$post_meta = $_post_meta;
		}
		self::save_cache( $post_id, $field['name'], $post_meta );
		return $post_meta;
	}

	/**
	 * save_settings_posts_cache
	 * @param int $post_type
	 * @param array $posts
	 */
	protected static function save_settings_posts_cache( $post_type, array $posts = array() ) {
		self::$settings_posts_cache[$post_type] = $posts;
	}

	/**
	 * get_settings_posts
	 * @param int $post_type
	 * @param array $settings
	 */
	public static function get_settings_posts( $post_type ) {
		$posts = array();
		if ( isset( self::$settings_posts_cache[$post_type] ) ) {
			return self::$settings_posts_cache[$post_type];
		}
		$posts = get_posts( array(
			'post_type'      => SCF_Config::NAME,
			'posts_per_page' => -1,
			'meta_query'     => array(
				array(
					'key'     => SCF_Config::PREFIX . 'condition',
					'compare' => 'LIKE',
					'value'   => $post_type,
				),
			),
		) );
		self::save_settings_posts_cache( $post_type, $posts );
		return $posts;
	}

	/**
	 * save_settings_cache
	 * @param int $post_type
	 * @param array $settings
	 */
	protected static function save_settings_cache( $post_type, array $settings = array() ) {
		self::$settings_cache[$post_type] = $settings;
	}

	/**
	 * get_settings
	 * @param int $post_type
	 * @param array $settings
	 */
	public static function get_settings( $post_type ) {
		$settings = array();
		if ( isset( self::$settings_cache[$post_type] ) ) {
			return self::$settings_cache[$post_type];
		}
		if ( empty( $settings ) ) {
			$cf_posts = self::get_settings_posts( $post_type );
			foreach ( $cf_posts as $_post ) {
				$setting = array();
				$_setting = get_post_meta( $_post->ID, SCF_Config::PREFIX . 'setting', true );
				if ( is_array( $_setting ) ) {
					$setting = $_setting;
				}
				$settings[] = $setting;
			}
		}
		self::save_settings_cache( $post_type, $settings );
		return $settings;
	}

	/**
	 * save_repeat_multiple_data_cache
	 * @param int $post_id
	 * @param mixed $repeat_multiple_data
	 */
	protected static function save_repeat_multiple_data_cache( $post_id, $repeat_multiple_data ) {
		self::$repeat_multiple_data_cache[$post_id] = $repeat_multiple_data;
	}

	/**
	 * get_repeat_multiple_data
	 * @param int $post_id
	 * @return mixed
	 */
	public static function get_repeat_multiple_data( $post_id ) {
		$repeat_multiple_data = array();
		if ( isset( self::$repeat_multiple_data_cache[$post_id] ) ) {
			return self::$repeat_multiple_data_cache[$post_id];
		}
		if ( empty( $repeat_multiple_data ) ) {
			$_repeat_multiple_data = get_post_meta( $post_id, SCF_Config::PREFIX . 'repeat-multiple-data', true );
			if ( $_repeat_multiple_data ) {
				$repeat_multiple_data = $_repeat_multiple_data;
			}
		}
		self::save_repeat_multiple_data_cache( $post_id, $repeat_multiple_data );
		return $repeat_multiple_data;
	}
}