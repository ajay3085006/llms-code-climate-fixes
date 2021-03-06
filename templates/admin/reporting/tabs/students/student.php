<?php
/**
 * Single Student View
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! is_admin() ) { exit; }
?>
<section class="llms-gb-student">

	<header class="llms-reporting-breadcrumbs">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=llms-reporting' ) ); ?>"><?php _e( 'Students', 'lifterlms' ); ?></a>
		<?php do_action( 'llms_reporting_student_tab_breadcrumbs' ); ?>
	</header>

	<header class="llms-gb-student-header">

		<?php echo $student->get_avatar( 64 ); ?>
		<div class="llms-gb-student-info">
			<h2><a href="<?php echo get_edit_user_link( $student->get_id() ); ?>"><?php echo $student->get_name(); ?></a></h2>
			<h5><a href="mailto:<?php echo $student->get( 'user_email' ); ?>"><?php echo $student->get( 'user_email' ); ?></a></h5>
		</div>

	</header>

	<nav class="llms-nav-tab-wrapper llms-nav-secondary">
		<ul class="llms-nav-items">
		<?php foreach ( $tabs as $name => $label ) : ?>
			<li class="llms-nav-item<?php echo ( $current_tab === $name ) ? ' llms-active' : ''; ?>">
				<a class="llms-nav-link" href="<?php echo LLMS_Admin_Reporting::get_stab_url( $name ) ?>">
					<?php echo $label; ?>
				</a>
		<?php endforeach; ?>
		</ul>
	</nav>

	<section class="llms-gb-tab">
		<?php llms_get_template( 'admin/reporting/tabs/students/' . $current_tab . '.php', array(
			'student' => $student,
		) ); ?>
	</section>

</section>
