<?php
/**
 * Add-on kit: consent-category helpers.
 *
 * @package Soderlind\Kjeks
 */

declare(strict_types=1);

namespace Soderlind\Kjeks\AddonKit;

use Soderlind\Kjeks\Consent\Categories as CoreCategories;

/**
 * Gating-category helpers shared by Kjeks add-ons.
 *
 * Public, versioned API: add-ons `use` this to offer the same category picker
 * everywhere instead of re-deriving the list. Backed by the core category
 * registry, so any category added via the `kjeks_categories` filter appears
 * here too. The `necessary` category is never offered — you do not gate what
 * must always run.
 *
 * @since 1.2.0
 */
final class Categories {

	public const OFF = 'off';

	/**
	 * Selectable gating categories, slug => label.
	 *
	 * @return array<string, string>
	 */
	public static function choices(): array {
		$all    = CoreCategories::all();
		$result = array();
		foreach ( CoreCategories::optional() as $slug ) {
			$result[ $slug ] = isset( $all[ $slug ]['label'] ) ? (string) $all[ $slug ]['label'] : $slug;
		}

		return $result;
	}

	/**
	 * Whether a slug is a valid gating category (optional, non-necessary).
	 */
	public static function is_valid( string $slug ): bool {
		return CoreCategories::exists( $slug ) && ! CoreCategories::is_required( $slug );
	}

	/**
	 * Coerces a submitted value to a valid gating category.
	 *
	 * @param string $value     Raw value.
	 * @param bool   $allow_off Whether `off` (ungated) is permitted.
	 * @param string $fallback  Category to use when the value is invalid.
	 */
	public static function coerce( string $value, bool $allow_off = false, string $fallback = CoreCategories::MARKETING ): string {
		if ( $allow_off && self::OFF === $value ) {
			return self::OFF;
		}

		return self::is_valid( $value ) ? $value : $fallback;
	}

	/**
	 * Renders a category `<select>`.
	 *
	 * @param string $name      Field name attribute.
	 * @param string $id        Field id attribute.
	 * @param string $current   Currently selected value.
	 * @param bool   $allow_off Whether to offer an "Off (not gated)" option.
	 */
	public static function render_select( string $name, string $id, string $current, bool $allow_off = false ): void {
		?>
		<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>">
			<?php if ( $allow_off ) : ?>
				<option value="off" <?php selected( $current, self::OFF ); ?>><?php esc_html_e( 'Off (not gated)', 'kjeks' ); ?></option>
			<?php endif; ?>
			<?php foreach ( self::choices() as $slug => $label ) : ?>
				<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $current, $slug ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php
	}
}
