<?php
/**
 *
 * Class : WizardStep3  (Clone a form wizard)
 *
 * @copyright 2007 Loughborough University
 * @license http://www.gnu.org/licenses/gpl.txt
 * @version 1.0.0.0
 *
 */

class WizardStep3 {

  // Public
  public $wizard = null;
  public $step = 3;

  /*
  * CONSTRUCTOR
  */
  function WizardStep3(&$wizard) {
    $this->wizard =& $wizard;

    $this->wizard->back_button = null;
    $this->wizard->next_button = null;
    $this->wizard->cancel_button = null;
  }// /WizardStep3()

  function head() {
?>
<script language="JavaScript" type="text/javascript">
<!--

  function body_onload() {
  }// /body_onload()

//-->
</script>
<?php
  }// /->head()

  function form() {
    global $_module_id;

    $DB =& $this->wizard->get_var('db');
    $user =& $this->wizard->get_var('user');

    $errors = null;

    $existing_form = new Form($DB);
    $existing_form->load($this->wizard->get_field('form_id'));

    $clone_form =& $existing_form->get_clone();
    $clone_form->name = $this->wizard->get_field('clone_form_name');
    $clone_form->modules[] = $_module_id;

    // If errors, show them
    if (is_array($errors)) {
      $this->wizard->back_button = gettext('&lt; Back');
      $this->wizard->cancel_button = gettext('Cancel');
      echo('<p><strong>'.gettext('Unable to create your new form.').'</strong></p>');
      echo('<p>'.gettext('To correct the problem, click <em>back</em> and amend the details entered.').'</p>');
    } else {// Else.. create the form!
      if ($clone_form->save()) {
?>
        <p><strong><?php echo gettext('Your new assessment form has been created.');?></strong></p>
        <p style="margin-top: 20px;"><?php echo sprintf(gettext('To add questions and marking information to your new form, you can use the <a href="../edit/edit_form.php?f=%s">form editor</a>.'), $clone_form->id);?></p>
        <p style="margin-top: 20px;"><?php echo gettext('Alternatively, you can return to <a href="../">my forms</a>, or to the <a href="../../../">WebPA home page</a>.');?></p>
<?php
      } else {
?>
        <p><strong><?php echo gettext('An error occurred while trying to create your new assessment form.');?></strong></p>
        <p><?php echo gettext('You may be able to correct the problem by clicking <em>back</em>, and then <em>next</em> again.');?></p>
<?php
      }
    }
  }// /->form()

  function process_form() {
    $this->wizard->_fields = array(); // kill the wizard's stored fields
    return null;
  }// /->process_form()

}// /class: WizardStep3

?>
