<?php
/**
 *
 * Report: Individual Student Feedback
 *
 *
 * @copyright 2007 Loughborough University
 * @license http://www.gnu.org/licenses/gpl.txt
 * @version 1.0.0.0
 *
 */

require_once("../../includes/inc_global.php");
require_once("../../includes/classes/class_assessment.php");
require_once("../../includes/classes/class_form.php");
require_once("../../includes/classes/class_group_handler.php");
require_once(DOC__ROOT . 'includes/classes/class_algorithm_factory.php');

if (!check_user($_user, APP__USER_TYPE_STUDENT)){
  header('Location:'. APP__WWW .'/logout.php?msg=denied');
  exit;
}

// --------------------------------------------------------------------------------
// Process GET/POST

$assessment_id = fetch_GET('a');

$range = 0.1;   // Multiplied by average score to give AVG range

// --------------------------------------------------------------------------------

function extract_max($input_array){
  return array_keys($input_array, max($input_array));
}

function extract_min($input_array){
  return array_keys($input_array, min($input_array));
}

$assessment = new Assessment($DB);
if (!$assessment->load($assessment_id)) {
  $assessment = null;
  echo(gettext('Error: The requested assessment could not be loaded.'));
  exit;
} else {

  // ----------------------------------------
  // Get the marking parameters used for the marksheet this report will display
  // These are not used in this particular page anyway... we just need some dummy values

  $marking_params['weighting']= 100;
  $marking_params['penalty'] = 0;
  $marking_params['penalty_type'] = '%';
  $marking_params['tolerance'] = null;
  $marking_params['grading'] = 'numeric' ;
  $marking_params['algorithm'] = 'webpa';

  // ----------------------------------------
  // Get a list of the groups, and their marks, used in this assessment
  $groups_and_marks = $assessment->get_group_marks();

  // ----------------------------------------
  // Get the appropriate algorithm and calculate the grades
  $algorithm = AlgorithmFactory::get_algorithm($marking_params['algorithm']);

  if (!$algorithm) {
    echo(gettext('Error: The requested algorithm could not be loaded.'));
    exit;
  } else {
    $algorithm->set_grade_ordinals($ordinal_scale);
    $algorithm->set_assessment($assessment);
    $algorithm->set_marking_params($marking_params);
    $algorithm->calculate();

    $submissions = $algorithm->get_submitters();
    $webpa_scores = $algorithm->get_webpa_scores();

    $intermediate_grades = $algorithm->get_intermediate_grades();
    $grades = $algorithm->get_grades();

    $penalties = $algorithm->get_penalties();
    if (!$penalties) { $penalties = array(); }


    $questions = $algorithm->get_questions();

    $group_names = $algorithm->get_group_names();
    $group_members = $algorithm->get_group_members();
    $member_ids = array_keys($webpa_scores);

    $member_names = array();

    for ($i =0; $i<count($group_members); $i++){
      $array_key = array_keys($group_members);
      $temp = $group_members[$array_key[$i]];
      for ($j=0; $j<count($temp);$j++){
        array_push($member_names, $CIS->get_user($temp[$j]));
      }
    }

    $group_handler = new GroupHandler();
    $collection =& $group_handler->get_collection($assessment->get_collection_id());

    $form = $assessment->get_form();

  }// /if-else(is algorithm)

}// /if-else(is assessment)


$UI->page_title = APP__NAME .' '.gettext('Feedback Report');
$UI->head();
?>
<style type="text/css">
<!--

#side_bar { display: none; }
#main { margin: 0px; }

-->
</style>
<?php
$UI->content_start();
?>

<div class="content_box">



<h2><?php echo($assessment->name); ?></h2>
<p style="margin-bottom: 2em;"><?php echo sprintf(gettext('The following is based on your relative contribution in the group, measured from the self and peer assessment %s only and does <b>not</b> take account of the overall group %s for the project.'), APP__MARK_TEXT, APP__MARK_TEXT); ?></p>
</p>


<?php
if ( (!$assessment) || (!$group_names) ) {
?>
  <div class="warning_box">
    <p style="font-weight: bold;"><?php echo gettext('WebPA could not generate feedback for your assessment.'); ?></p>
    <p><?php echo gettext('There was a technical problem retrieving your information.'); ?></p>
  </div>
<?php
} else {
  // ----------------------------------------
  // Get the group this user belongs to
  $group_id = null;
  if ($collection) {
    // Get the group this user belongs to
    $groups = $collection->get_member_groups($_user->id);
    if ($groups) {
      $group =& $groups[0];
      $group_id = $group->id;
    }
  }

  $g_members = $group_members[$group_id];
  $member_count = count($group_members[$group_id]);

  $question_count = count($questions);

  $awarded_total = array();

  foreach($questions as $question_id => $question ) {

    $awarded_total[$question_id] = 0;

    foreach($g_members as $i => $member_id) {

      foreach($g_members as $j => $target_member_id) {
        //pull out the details for the one group member we are looking for
        if ($target_member_id == $_user->id){
          $awarded_marks[$question_id][$member_id]['mark_received'] = $algorithm->get_member_response($group_id, $member_id, $question_id, $target_member_id);
          $awarded_total[$question_id] +=  $algorithm->get_member_response($group_id, $member_id, $question_id, $target_member_id);
        }

      }

    }

  }

  $match = 0;

  for($q=0; $q<$question_count; $q++){
    if(!$q==0){
      if ($q == $question_count-1){
        if($awarded_marks[$q-1]== $awarded_marks[$q]){
          $match +=1;
        }
      }else{
        if($awarded_marks[$q]==$awarded_marks[$q+1]){
          $match +=1;
        }
      }
    }else{
      if($awarded_marks[$q]==$awarded_marks[$question_count-1]){
          $match +=1;
        }
    }
  }

  for ($q=0; $q<$question_count; $q++){
    $min_score[$q] = min($awarded_marks[$q]);
    $max_score[$q] = max($awarded_marks[$q]);

  }

  //check to see if the answer are all the same for all questions
  if ($match==$question_count) {

    echo "<p>".sprintf(gettext("Your group has %s everyone equally, hence we are unable to provide feedback on your relative performance for different assessment criteria."), APP__MARK_TEXT)."</p>";

  } else {

    //get the fractional values and work out the greatest
    for ($question = 0; $question<$question_count; $question++){
      $max_score_per_question[$question] = ($max_score[$question]['mark_received'] / $awarded_total[$question])*4;
      $min_score_per_question[$question] = ($min_score[$question]['mark_received'] / $awarded_total[$question])*4;
    }
    //display best based on the normalised mark when there are more that 1-2 best criteria
    //extract_max which will return the array keys
    $returned_max = extract_max($max_score_per_question);

    $msg_failure = '';

    //check the returned is an array
    if (is_array($returned_max)) {
      $returned_max_count = count($returned_max);

      if ($returned_max_count>1){
        //compare the two top marks
        if ($max_score[$returned_max[0]]['mark_received']==$max_score[$returned_max[1]]['mark_received']){
          //leave in incase we want to offer more than one result
          $element = $form->get_question($returned_max[0]);
          echo "<h3>".gettext("Your strongest contribution within this project was rated by your group as:")."</h3>";
          echo "<p><b>" . $element['text']['_data'];
          echo "</b><br/>" . $element['desc']['_data'];
          echo "</p>";
        }else{
          $element = $form->get_question($returned_max[0]);
          echo "<h3>".gettext("Your strongest contribution within this project was rated by your group as:")."</h3>";
          echo "<p><b>" . $element['text']['_data'];
          echo "</b><br/>" . $element['desc']['_data'];
          echo "</p>";
        }
      }

    } else {
      $msg_failure = "<p>".gettext("WebPA has been unable to generate any feedback")."</p>";
    }

    echo "<br/><br/>";

  //only display if the score is below the median and one mark below the others
  //No area for development should be identified if their lowest score is above the median AND
  //is greater than 80% of their highest score

    $returned_min = extract_min($min_score_per_question);
    if (is_array($returned_min)) {
      $returned_min_count = count($returned_min);

      if ($returned_min_count>1) {
        if ($min_score[$returned_min[0]]['mark_received']==$min_score[$returned_min[1]]['mark_received']) {
          $element = $form->get_question($returned_min[0]);
          $range = $element['range']['_data'];
          //split the string up
          $split_range = split('-',$range);
          //calculate the median
          $median = round(($split_range[0]+$split_range[1])/2);

          //compare to ensure that the lowest if below the median
          if ($min_score_per_question[$returned_min[0]]<$median) {
            echo "<h3>".gettext("An area you may wish to develop is your contribution to:")."</h3>";
            echo "<p><b>" . $element['text']['_data'];
            echo "</b><br/>" . $element['desc']['_data'];
            echo "</p>";
          }
        } else {
          $element = $form->get_question($returned_min[0]);
          echo "<h3>".gettext("An area you may wish to develop is your contribution to:")."</h3>";
          echo "<p><b>" . $element['text']['_data'];
          echo "</b><br/>" . $element['desc']['_data'];
          echo "</p>";
        }
      } else {
        $element = $form->get_question($returned_min[0]);
        $range = $element['range']['_data'];
        //split the string up
        $split_range = split('-',$range);
        //calculate the median
        $median = round(($split_range[0]+$split_range[1])/2);

        //compare to ensure that the lowest if below the median
        if ($min_score_per_question[$returned_min[0]]<$median){
          echo "<h3>".gettext("An area you may wish to develop is your contribution to:")."</h3>";
          echo "<p><b>" . $element['text']['_data'];
          echo "</b><br/>" . $element['desc']['_data'];
          echo "</p>";
        }
      }
    } else {
      $msg_failure = "<p>".gettext("WebPA has been unable to generate any feedback")."</p>";
    }
  }
?>

<br/>

  <!-- Chart -->
  <h3>Chart</h3>

<?php
// get the labels
$labels = array();
$i = 1;
foreach($questions as $question){
  $question_label = "Q" . $i;
  $labels[] = $question_label;
  $i++;
}
$labels = '"' . implode('","', $labels) . '"';

// get the values
$values_average = array();
$values_min = array();
$values_max = array();
$uid = $_user->id;

for ($q=0; $q<$question_count; $q++){
  $total = 0;
  $count_votes = 0;
  foreach($awarded_marks[$q] as $awarded_mark){
    if($awarded_mark['mark_received'] != null){
      $total = $total + $awarded_mark['mark_received'];
      $count_votes++;
      if(empty($values_min[$q]) || $values_min[$q] > $awarded_mark['mark_received']){
        $values_min[$q] = $awarded_mark['mark_received'];
      }
      if(empty($values_max[$q]) || $values_max[$q] < $awarded_mark['mark_received']){
        $values_max[$q] = $awarded_mark['mark_received'];
      }
    }
  }
  $values_average[$q] = $total / $count_votes;
}

$values_min = implode(',', $values_min);
$values_max = implode(',', $values_max);
$values_average = implode(',', $values_average);
?>
<br/>
  <canvas id="feedbackChart" width="600" height="400"></canvas>
<br />
<?php

$i = 1;
foreach($questions as $question){
  $question_label = "Question " . $i;
  echo "<span><strong>$question_label: </strong>" . $question["text"]["_data"] . "</span><br/>";
  $i++;
}

?>

  <script src="../../js/Chart.min.js" type="text/javascript"></script>
  <script>
    var data = {
      labels: [<?php echo $labels; ?>],
      datasets: [
        {
          label: "Max",
          fillColor: "rgba(220,220,220,0.2)",
          strokeColor: "rgba(220,220,220,1)",
          pointColor: "rgba(220,220,220,1)",
          pointStrokeColor: "#fff",
          pointHighlightFill: "#fff",
          pointHighlightStroke: "rgba(220,220,220,1)",
          data: [<?php echo $values_max; ?>]
        },
        {
          label: "Min",
          fillColor: "rgba(220,220,220,0.2)",
          strokeColor: "rgba(220,220,220,1)",
          pointColor: "rgba(220,220,220,1)",
          pointStrokeColor: "#fff",
          pointHighlightFill: "#fff",
          pointHighlightStroke: "rgba(220,220,220,1)",
          data: [<?php echo $values_min; ?>]
        },
        {
          label: "Average",
          fillColor: "rgba(151,187,205,0.2)",
          strokeColor: "rgba(151,187,205,1)",
          pointColor: "rgba(151,187,205,1)",
          pointStrokeColor: "#fff",
          pointHighlightFill: "#fff",
          pointHighlightStroke: "rgba(151,187,205,1)",
          data: [<?php echo $values_average; ?>]
        }
      ]
    };
    var options = {
      multiTooltipTemplate: "<%if (datasetLabel){%><%=datasetLabel%>: <%}%><%= value %>",
    }
    var ctx = document.getElementById("feedbackChart").getContext("2d");
    var RadarChart = new Chart(ctx).Radar(data, options);
  </script>

  <?php
  echo "  </div>\n";
}
?>
<br />
<form action="#" method="post" name="feedbackform">
<div style="text-align: center;">
  <input type="button" name="closebutton" value="<?php echo gettext('close');?>" onclick="window.close();" />
</div>
</form>
</div>

<?php

$UI->content_end(false, false, false);

?>
