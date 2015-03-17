<?php
/**
 * Smart_Custom_Fields_Meta
 * Version    : 1.0.0
 * Author     : Takashi Kitajima
 * Created    : March 17, 2015
 * Modified   : 
 * License    : GPLv2
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */
class Smart_Custom_Fields_Meta {

	/**
	 * 投稿のメタデータを扱うか、ユーザーのメタデータを扱うか
	 * @var string post or user
	 */
	protected $type = 'post';

	/**
	 * @param string $type 投稿タイプもしくは smart-cf-profile
	 */
	public function __construct( $type ) {
		if ( in_array( $post_type, get_post_types() ) ) {
			$this->type = 'user';
		} elseif ( in_array( $post_type, array_keys( get_editable_roles() ) ) ) {
			$this->type = 'post';
		} else {
			throw new Exception( 'Invalid post type error.' );
		}
	}

	/**
	 * メタデータを取得
	 *
	 * @param int $id Post ID もしくは Author ID
	 * @param string $key メタキー
	 * @param bool $single false だと配列で取得、true だと文字列で取得
	 * @return mixed
	 */
	public function get_meta( $id, $key = '', $single = false ) {
		$function = "get_{$this->type}_meta";
		return $function( $id, $key, $single );
	}

	/**
	 * メタデータを更新。そのメタデータが存在しない場合は追加。
	 *
	 * @param int $id Post ID もしくは Author ID
	 * @param string $key メタキー
	 * @param mixed $value 保存する値
	 * @param mixed $prev_value 指定された場合、この値のものだけを上書き
	 * @return int|false Meta ID
	 */
	public function update_meta( $id, $key, $value, $prev_value = '' ) {
		$function = "update_{$this->type}_meta";
		return $function( $id, $key, $value, $prev_value );
	}

	/**
	 * メタデータを追加
	 *
	 * @param int $id Post ID もしくは Author ID
	 * @param string $key メタキー
	 * @param mixed $value 保存する値
	 * @param bool $unique キーをユニークにするかどうか
	 * @return int|false Meta ID
	 */
	public function add_meta( $id, $key, $value, $unique = false ) {
		$function = "add_{$this->type}_meta";
		return $function( $id, $key, $value, $unique );
	}

	/**
	 * メタデータを削除
	 *
	 * @param int $id Post ID もしくは Author ID
	 * @param string $key メタキー
	 * @param mixed $value 指定した場合、その値をもつメタデータのみ削除
	 * @return bool
	 */
	public function delete_meta( $id, $key, $value = '' ) {
		$function = "delete_{$this->type}_meta";
		return $function( $id, $key, $value );
	}
}