<?php

class qformat_exlim extends qformat_default {
    public function provide_import() {
        return true;
    }

    public function can_import_file($file) {
        $mimetypes = array(
            mimeinfo('type', '.xls'),
            mimeinfo('type', '.xlsx')
        );
        return in_array($file->get_mimetype(), $mimetypes);
    }

    /**
     * Return complete file within an array, one item per line
     * @param string filename name of file
     * @return mixed contents array or false on failure
     */
    protected function readdata($filename) {
        global $CFG;
        if (is_readable($filename)) {
            $header = $filearray = array();
            require_once($CFG->dirroot . '/question/format/exlim/spreadsheet-reader/php-excel-reader/excel_reader2.php');
            require_once($CFG->dirroot . '/question/format/exlim/spreadsheet-reader/SpreadsheetReader.php');
            $Reader = new SpreadsheetReader($filename);

            foreach ($Reader as $Row)
            {
                if(empty($header)){ $header = $Row; }
                else{
                    $temp = array();
                    foreach($Row as $idx => $val){
                        if(isset($header[$idx])){
                            $temp[$header[$idx]] = $val;
                        }
                    }
                    $filearray[] = $temp;
                }
            }

            return $filearray;

        }
        return false;
    }

    /**
     * Parse the array of lines into an array of questions
     * this *could* burn memory - but it won't happen that much
     * so fingers crossed!
     * @param array of lines from the input file.
     * @param stdClass $context
     * @return array (of objects) question objects.
     */
    protected function readquestions($lines) {
        global $CFG;
        $questions = array();
    	foreach ($lines as $line)
    	{
            if ($question = $this->readquestion($line)) {
                if($question !== false){
                    $questions[] = $question;
                }
            }
    	}
        return $questions;
    }

    public function readquestion($line) {
        // Given an array of lines known to define a question in this format, this function
        // converts it into a question object suitable for processing and insertion into Moodle.

        $question = $this->defaultquestion();
        $question->generalfeedbackformat = FORMAT_PLAIN;
        $question->questiontextformat = FORMAT_PLAIN;
        $question->generalfeedback = isset($line['feedback']) ? $line['feedback'] : false;
        $text = $question->questiontext = isset($line['question']) ? $line['question'] : false;
        $question->name = isset($line['name']) ? $line['name'] : false;
        // Set question name if not already set.
        if ($question->name === false) {
            $question->name = $this->create_default_question_name($question->questiontext, get_string('questionname', 'question'));
        }
        /*
        description,essay,numerical,multichoice,match,truefalse,shortanswer
        */
        $question->qtype = isset($line['type']) ? $line['type'] : false;
        $answertext = isset($line['answer']) ? $line['answer'] : false;

        if (!isset($question->qtype)) {
            $giftqtypenotset = get_string('giftqtypenotset', 'qformat_gift');
            $this->error($giftqtypenotset, $text);
            return false;
        }


        switch ($question->qtype) {
            case 'description':
                $question->defaultmark = 0;
                $question->length = 0;
                return $question;

            case 'essay':
                $question->responseformat = 'editor';
                $question->responserequired = 1;
                $question->responsefieldlines = 15;
                $question->attachments = 0;
                $question->attachmentsrequired = 0;
                $question->graderinfo = array(
                        'text' => '', 'format' => FORMAT_HTML, 'files' => array());
                $question->responsetemplate = array(
                        'text' => '', 'format' => FORMAT_HTML);
                return $question;

            case 'multichoice':
                $question->single = isset($line['single_choice']) ? $line['single_choice'] : 1; // Multiple answers are enabled if no single answer is 100% correct.
                $question = $this->add_blank_combined_feedback($question);
                $options = array();
                foreach($line as $key => $each){
                    if(substr($key, 0, 4) == 'ans_'){
                        $options[substr($key, 4)] = $each;
                    }
                }
                $answer = explode(',',$answertext);

                // to ensure the question had more than one options to choose
                if (!$this->check_answer_count(2, $options, $text)) {
                    return false;
                }

                $lop = 0;
                foreach ($options as $key => $opt) {
                    $opt = trim($opt);
                    // Determine answer weight.
                    if (array_search($key, $answer) !== false) {
                        $answerweight = 1;
                    } else {     // Default, i.e., wrong anwer.
                        $answerweight = 0;
                    }
                    $question->answer[$lop] = $this->text_field($opt);
                    $question->feedback[$lop] = $this->text_field($question->questiontextformat);
                    $question->feedbackformat[$lop] = $question->generalfeedback;
                    $question->fraction[$lop] = $answerweight;
                    $lop++;
                }  // End foreach answer.

                // var_dump($question);die();
                return $question;

            case 'match':
                // $question = $this->add_blank_combined_feedback($question);
                // $answersMatch = $answers = array();
                // foreach($line as $key => $each){
                //     if(substr($key, 0, 4) == 'ans_'){
                //         $answers[substr($key, 4)] = $each;
                //     }
                //     if(substr($key, 0, 9) == 'ansmatch_'){
                //         $answersMatch[substr($key, 9)] = $each;
                //     }
                // }
                //
                // if (!$this->check_answer_count(2, $answers, $text)) {
                //     return false;
                // }
                // $lop = 0;
                // foreach ($answers as $key => $answer) {
                //     if (!empty($answer)) {
                //         $answer = trim($answer);
                //         if (!isset($answersMatch[$key])) {
                //             $this->error(get_string('giftmatchingformat', 'qformat_exlim'), $answer);
                //             return false;
                //         }
                //         $question->subquestions[$lop] = $this->text_field($answersMatch[$key]);
                //         $question->subanswers[$lop] = $this->text_field($answer);
                //         $lop++;
                //     }
                // }
                // // $question->answer[$lop] = $this->text_field($opt);
                // // var_dump($question);die();
                // return $question;
                return false;

            case 'truefalse':
                if (strtolower($answertext) == "t") {
                    $question->correctanswer = 1;
                } else {
                    $question->correctanswer = 0;
                }
                $question->feedbacktrue = $this->text_field($question->questiontextformat);
                $question->feedbackfalse = $this->text_field($question->questiontextformat);
                $question->penalty = 1;
                return $question;

            // case 'shortanswer':
            //     // Shortanswer question.
            //     $answers = explode("=", $answertext);
            //     if (isset($answers[0])) {
            //         $answers[0] = trim($answers[0]);
            //     }
            //     if (empty($answers[0])) {
            //         array_shift($answers);
            //     }
            //
            //     if (!$this->check_answer_count(1, $answers, $text)) {
            //         return false;
            //     }
            //
            //     foreach ($answers as $key => $answer) {
            //         $answer = trim($answer);
            //
            //         // Answer weight.
            //         if (preg_match($giftanswerweightregex, $answer)) {    // Check for properly formatted answer weight.
            //             $answerweight = $this->answerweightparser($answer);
            //         } else {     // Default, i.e., full-credit anwer.
            //             $answerweight = 1;
            //         }
            //
            //         list($answer, $question->feedback[$key]) = $this->commentparser(
            //                 $answer, $question->questiontextformat);
            //
            //         $question->answer[$key] = $answer['text'];
            //         $question->fraction[$key] = $answerweight;
            //     }
            //
            //     return $question;
            //
            // case 'numerical':
            //     // Note similarities to ShortAnswer.
            //     $answertext = substr($answertext, 1); // Remove leading "#".
            //
            //     // If there is feedback for a wrong answer, store it for now.
            //     if (($pos = strpos($answertext, '~')) !== false) {
            //         $wrongfeedback = substr($answertext, $pos);
            //         $answertext = substr($answertext, 0, $pos);
            //     } else {
            //         $wrongfeedback = '';
            //     }
            //
            //     $answers = explode("=", $answertext);
            //     if (isset($answers[0])) {
            //         $answers[0] = trim($answers[0]);
            //     }
            //     if (empty($answers[0])) {
            //         array_shift($answers);
            //     }
            //
            //     if (count($answers) == 0) {
            //         // Invalid question.
            //         $giftnonumericalanswers = get_string('giftnonumericalanswers', 'qformat_gift');
            //         $this->error($giftnonumericalanswers, $text);
            //         return false;
            //     }
            //
            //     foreach ($answers as $key => $answer) {
            //         $answer = trim($answer);
            //
            //         // Answer weight.
            //         if (preg_match($giftanswerweightregex, $answer)) {    // Check for properly formatted answer weight.
            //             $answerweight = $this->answerweightparser($answer);
            //         } else {     // Default, i.e., full-credit anwer.
            //             $answerweight = 1;
            //         }
            //
            //         list($answer, $question->feedback[$key]) = $this->commentparser(
            //                 $answer, $question->questiontextformat);
            //         $question->fraction[$key] = $answerweight;
            //         $answer = $answer['text'];
            //
            //         // Calculate Answer and Min/Max values.
            //         if (strpos($answer, "..") > 0) { // Optional [min]..[max] format.
            //             $marker = strpos($answer, "..");
            //             $max = trim(substr($answer, $marker + 2));
            //             $min = trim(substr($answer, 0, $marker));
            //             $ans = ($max + $min)/2;
            //             $tol = $max - $ans;
            //         } else if (strpos($answer, ':') > 0) { // Standard [answer]:[errormargin] format.
            //             $marker = strpos($answer, ':');
            //             $tol = trim(substr($answer, $marker+1));
            //             $ans = trim(substr($answer, 0, $marker));
            //         } else { // Only one valid answer (zero errormargin).
            //             $tol = 0;
            //             $ans = trim($answer);
            //         }
            //
            //         if (!(is_numeric($ans) || $ans = '*') || !is_numeric($tol)) {
            //                 $errornotnumbers = get_string('errornotnumbers');
            //                 $this->error($errornotnumbers, $text);
            //             return false;
            //         }
            //
            //         // Store results.
            //         $question->answer[$key] = $ans;
            //         $question->tolerance[$key] = $tol;
            //     }
            //
            //     if ($wrongfeedback) {
            //         $key += 1;
            //         $question->fraction[$key] = 0;
            //         list($notused, $question->feedback[$key]) = $this->commentparser(
            //                 $wrongfeedback, $question->questiontextformat);
            //         $question->answer[$key] = '*';
            //         $question->tolerance[$key] = '';
            //     }
            //
            //     return $question;

            default:
                $this->error(get_string('giftnovalidquestion', 'qformat_gift'), $text);
                return false;

        }
    }

    protected function check_answer_count($min, $answers, $text) {
        $countanswers = count($answers);
        if ($countanswers < $min) {
            $this->error(get_string('importminerror', 'qformat_gift'), $text);
            return false;
        }

        return true;
    }

    protected function text_field($text) {
        return array(
            'text' => htmlspecialchars(trim($text), ENT_NOQUOTES),
            'format' => FORMAT_PLAIN,
            'files' => array(),
        );
    }

}
