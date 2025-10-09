<?php
/**
 * Telemetry aggregation for the Mon Affichage Articles plugin.
 *
 * @package Mon_Affichage_Articles
 */

defined( 'ABSPATH' ) || exit;

/**
 * Collects client-side interaction events and exposes an admin dashboard.
 */
class My_Articles_Telemetry {
    const OPTION_KEY = 'my_articles_telemetry';

    /**
     * Bootstraps hooks.
     */
    public static function init() {
        add_action( 'my_articles_track_interaction', array( __CLASS__, 'record_interaction' ), 10, 3 );
        add_action( 'admin_menu', array( __CLASS__, 'register_dashboard_page' ) );
        add_action( 'admin_post_my_articles_reset_telemetry', array( __CLASS__, 'handle_reset_request' ) );
        add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
    }

    /**
     * Registers the persistent option used to store metrics.
     */
    public static function register_settings() {
        register_setting(
            'general',
            self::OPTION_KEY,
            array(
                'type'              => 'array',
                'sanitize_callback' => array( __CLASS__, 'sanitize_option' ),
                'show_in_rest'      => false,
                'default'           => array(),
            )
        );
    }

    /**
     * Ensures the telemetry option always uses the expected structure.
     *
     * @param mixed $value Raw option value.
     *
     * @return array
     */
    public static function sanitize_option( $value ) {
        if ( ! is_array( $value ) ) {
            $value = array();
        }

        $value['events'] = isset( $value['events'] ) && is_array( $value['events'] ) ? $value['events'] : array();
        $value['http']   = isset( $value['http'] ) && is_array( $value['http'] ) ? $value['http'] : array();

        if ( ! isset( $value['http']['statuses'] ) || ! is_array( $value['http']['statuses'] ) ) {
            $value['http']['statuses'] = array();
        }

        if ( ! isset( $value['http']['durations'] ) || ! is_array( $value['http']['durations'] ) ) {
            $value['http']['durations'] = array();
        }

        $value['last_updated'] = isset( $value['last_updated'] ) ? absint( $value['last_updated'] ) : 0;

        return $value;
    }

    /**
     * Retrieves the currently stored telemetry dataset.
     *
     * @return array
     */
    protected static function get_store() {
        return self::sanitize_option( get_option( self::OPTION_KEY, array() ) );
    }

    /**
     * Default container for a tracked event.
     *
     * @return array
     */
    protected static function get_default_event_stats() {
        return array(
            'requests'         => 0,
            'total'            => 0,
            'success'          => 0,
            'error'            => 0,
            'last_status'      => null,
            'status_counts'    => array(),
            'last_error'       => '',
            'last_duration_ms' => null,
            'duration_sum_ms'  => 0,
            'duration_samples' => 0,
            'last_event_time'  => 0,
        );
    }

    /**
     * Aggregates a tracked front-end interaction.
     *
     * @param string          $event_name Event identifier.
     * @param array|mixed     $detail     Event payload.
     * @param WP_REST_Request $request    Original REST request.
     */
    public static function record_interaction( $event_name, $detail, $request ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
        $event_key = is_string( $event_name ) ? sanitize_key( str_replace( array( ':', '/' ), '_', $event_name ) ) : '';

        if ( '' === $event_key ) {
            return;
        }

        $payload  = is_array( $detail ) ? $detail : array();
        $phase    = isset( $payload['phase'] ) ? sanitize_key( $payload['phase'] ) : '';
        $status   = isset( $payload['status'] ) ? intval( $payload['status'] ) : null;
        $duration = null;

        if ( isset( $payload['durationMs'] ) && is_numeric( $payload['durationMs'] ) ) {
            $duration = max( 0, floatval( $payload['durationMs'] ) );
        }

        $store = self::get_store();

        if ( ! isset( $store['events'][ $event_key ] ) ) {
            $store['events'][ $event_key ] = self::get_default_event_stats();
        }

        $stats = $store['events'][ $event_key ];

        if ( 'request' === $phase ) {
            $stats['requests'] += 1;
        }

        if ( 'success' === $phase ) {
            $stats['total']   += 1;
            $stats['success'] += 1;
        } elseif ( 'error' === $phase ) {
            $stats['total'] += 1;
            $stats['error'] += 1;

            if ( ! empty( $payload['errorMessage'] ) ) {
                $stats['last_error'] = sanitize_text_field( wp_strip_all_tags( (string) $payload['errorMessage'] ) );
            }
        }

        if ( null !== $status ) {
            $stats['last_status'] = $status;

            if ( ! isset( $stats['status_counts'][ $status ] ) ) {
                $stats['status_counts'][ $status ] = 0;
            }
            $stats['status_counts'][ $status ] += 1;

            if ( ! isset( $store['http']['statuses'][ $status ] ) ) {
                $store['http']['statuses'][ $status ] = 0;
            }
            $store['http']['statuses'][ $status ] += 1;
        }

        if ( null !== $duration ) {
            $stats['last_duration_ms'] = $duration;
            $stats['duration_sum_ms']  = isset( $stats['duration_sum_ms'] ) ? floatval( $stats['duration_sum_ms'] ) + $duration : $duration;
            $stats['duration_samples'] = isset( $stats['duration_samples'] ) ? intval( $stats['duration_samples'] ) + 1 : 1;

            if ( ! isset( $store['http']['durations'][ $event_key ] ) ) {
                $store['http']['durations'][ $event_key ] = array(
                    'total' => 0,
                    'count' => 0,
                );
            }

            $store['http']['durations'][ $event_key ]['total'] += $duration;
            $store['http']['durations'][ $event_key ]['count'] += 1;
        }

        $stats['last_event_time']     = time();
        $store['events'][ $event_key ] = $stats;
        $store['last_updated']         = time();

        update_option( self::OPTION_KEY, $store, false );
    }

    /**
     * Registers the telemetry dashboard within the custom post type menu.
     */
    public static function register_dashboard_page() {
        add_submenu_page(
            'edit.php?post_type=mon_affichage',
            __( 'Tableau de bord des interactions', 'mon-articles' ),
            __( 'Tableau de bord', 'mon-articles' ),
            'manage_options',
            'my-articles-telemetry',
            array( __CLASS__, 'render_dashboard_page' )
        );
    }

    /**
     * Handles telemetry reset requests.
     */
    public static function handle_reset_request() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Vous n’avez pas les permissions nécessaires pour effectuer cette action.', 'mon-articles' ) );
        }

        check_admin_referer( 'my-articles-reset-telemetry' );

        delete_option( self::OPTION_KEY );

        $redirect = isset( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ) : admin_url( 'edit.php?post_type=mon_affichage&page=my-articles-telemetry' );
        wp_safe_redirect( $redirect );
        exit;
    }

    /**
     * Outputs the telemetry dashboard.
     */
    public static function render_dashboard_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Vous n’avez pas accès à cette page.', 'mon-articles' ) );
        }

        $store        = self::get_store();
        $events       = $store['events'];
        $http         = $store['http'];
        $last_updated = $store['last_updated'];
        $reset_url    = admin_url( 'admin-post.php' );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Tableau de bord des interactions', 'mon-articles' ); ?></h1>

            <?php if ( $last_updated ) : ?>
                <p class="description">
                    <?php
                    printf(
                        /* translators: %s: human readable date. */
                        esc_html__( 'Dernière mise à jour : %s', 'mon-articles' ),
                        esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $last_updated ) )
                    );
                    ?>
                </p>
            <?php endif; ?>

            <?php if ( empty( $events ) ) : ?>
                <p><?php esc_html_e( 'Aucune interaction n’a encore été enregistrée.', 'mon-articles' ); ?></p>
            <?php else : ?>
                <table class="widefat striped">
                    <thead>
                    <tr>
                        <th scope="col"><?php esc_html_e( 'Événement', 'mon-articles' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Requêtes', 'mon-articles' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Succès', 'mon-articles' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Erreurs', 'mon-articles' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Durée moyenne (ms)', 'mon-articles' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Dernière durée (ms)', 'mon-articles' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Dernier statut HTTP', 'mon-articles' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Dernière erreur', 'mon-articles' ); ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ( $events as $event_id => $stats ) :
                        $avg = 0;
                        if ( ! empty( $stats['duration_samples'] ) ) {
                            $avg = $stats['duration_sum_ms'] / max( 1, $stats['duration_samples'] );
                        }
                        ?>
                        <tr>
                            <th scope="row"><?php echo esc_html( str_replace( '_', ' ', $event_id ) ); ?></th>
                            <td><?php echo esc_html( number_format_i18n( $stats['requests'] ) ); ?></td>
                            <td><?php echo esc_html( number_format_i18n( $stats['success'] ) ); ?></td>
                            <td><?php echo esc_html( number_format_i18n( $stats['error'] ) ); ?></td>
                            <td><?php echo esc_html( number_format_i18n( $avg, 2 ) ); ?></td>
                            <td><?php echo esc_html( number_format_i18n( floatval( $stats['last_duration_ms'] ), 2 ) ); ?></td>
                            <td><?php echo esc_html( $stats['last_status'] ? $stats['last_status'] : '—' ); ?></td>
                            <td><?php echo esc_html( $stats['last_error'] ? $stats['last_error'] : '—' ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <?php if ( ! empty( $http['statuses'] ) ) : ?>
                <h2><?php esc_html_e( 'Répartition des statuts HTTP', 'mon-articles' ); ?></h2>
                <ul>
                    <?php foreach ( $http['statuses'] as $code => $count ) : ?>
                        <li>
                            <?php
                            printf(
                                /* translators: 1: HTTP status code, 2: count. */
                                esc_html__( 'Statut %1$d : %2$s occurrence(s)', 'mon-articles' ),
                                intval( $code ),
                                esc_html( number_format_i18n( $count ) )
                            );
                            ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url( $reset_url ); ?>">
                <?php wp_nonce_field( 'my-articles-reset-telemetry' ); ?>
                <input type="hidden" name="action" value="my_articles_reset_telemetry" />
                <input type="hidden" name="redirect_to" value="<?php echo esc_attr( admin_url( 'edit.php?post_type=mon_affichage&page=my-articles-telemetry' ) ); ?>" />
                <?php submit_button( esc_html__( 'Réinitialiser les métriques', 'mon-articles' ), 'secondary' ); ?>
            </form>
        </div>
        <?php
    }
}
