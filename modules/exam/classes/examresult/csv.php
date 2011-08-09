<?php defined('SYSPATH') or die('No direct script access.');

class Examresult_Csv {

    /**
     * Array $_FILES['name']
     */
    private $_file;

    /**
     * Database_Mysql_Result
     */
    private $_exams;

    /**
     * Array array_keys = user_ids, array_values = Student Names
     */
    private $_students;

    /**
     * Array
     */
    private $_content;

    /**
     * Array of the form array('exam_id'=> int, 'user_id'=>int, 'marks' => float);
     */
    private $_datasets;

    /**
     * Array
     */
    private $_errors = array();

    /**
     * Array
     */
    private $_headings;

    /**
     * The exam marks array with exam_ids as the keys and total marks as the values
     */
    private $_exam_marks = array();

    /**
     * @param array $files_arr eg. $_FILES['csv']
     * @param Database_Mysql_Result $exams
     * 
     */
    public function __construct($files_arr, $exams, $students) {
        $this->_file = $files_arr;
        $this->_exams = $exams;
        $this->_students = $students;
        // assign exam marks array to an instance property for maximum marks validation
        $this->_exam_marks = $this->_exams->as_array('id', 'total_marks');
        if ($this->validate_filetype()) {
            $data = $this->csvcontent();
            $this->_headings = array_shift($data);
            $this->_content = $data;
            $this->_datasets = $this->csv_to_datasets();
        }
    }

    /**
     * Method to check whether the type of file uploaded and the data contained
     * by it are valid
     * @return boolean
     */
    public function validate() {
        if (!$this->_errors) {
            return true;
        }
        return false;
    }

    /**
     * Public getter for datasets
     * @return Array $_datasets 
     */
    public function datasets() {
        return $this->_datasets;
    }
    
    /**
     * Public getter for errors
     * @param string $key optional default = null
     * @param mixed (Array|String)
     */
    public function errors($key=null) {
        if ($key !== null) {
            return Arr::get($this->_errors, $key, '');
        }
        return $this->_errors;
    }

    /**
     * Method to read the csv file uploaded and return the content
     * @return array $filedata
     */
    public function csvcontent() {
        $filename = $this->_file['tmp_name'];
        $handle = fopen($filename, "r");        
        while (($data = fgetcsv($handle, 1000, ',')) !== FALSE){
            $filedata[] = $data;
        }
        return $filedata;
    }

    /**
     * Method to find the result sets for the exams from the csv data provided
     * @return array $datasets array_keys in order 'exam_id', 'user_id', 'marks'
     */
    private function csv_to_datasets() {
        $exams = $this->ordered_exams();
        $exam_marks = $this->_exams->as_array('id', 'total_marks');
        $datasets = array();
        foreach ($this->_content as $row) {
            $user_id = $row[0];
            $marks = array_slice($row, 2);
            foreach ($marks as $k=>$m) {                
                $exam_id = $exams[$k];
                $data = array(
                    'exam_id' => $exam_id,
                    'user_id' => $user_id,
                    'marks' => $m
                );
                if (!$this->validate_result($data)) {
                    break;
                }
                $datasets[] = $data;         
            }
        }
        return $datasets;
    }

    /**
     * Method to get the ordered list of exam_ids from the first line 
     * of the csv     
     * @return array of exam_ids in the order in which they appear in the 
     */
    private function ordered_exams() {
        $exams = $this->_exams->as_array('name', 'id');
        // remove the first two headings and get only exam headings
        $exam_names = array_slice($this->_headings, 2);
        $ordered_exams = array();
        foreach ($exam_names as $exam_name) {
            $ordered_exams[] = $exams[$exam_name];
        }
        return $ordered_exams;
    }

    private function validate_result($result) {
        return (
            $this->validate_marks($result) && 
            $this->validate_student($result['user_id'])
        );
    }

    /**
     * Method to validate the file uploaded. 
     * @return boolean if its a csv file
     */
    private function validate_filetype() {
        $filename = $this->_file['name'];
        $extension = explode(".",$filename);
        if (isset($extension[1]) && strtolower($extension[1]) === "csv") {
            return true;
        } else {
            $this->_errors['invalid_extension'] = 'Uploaded file not of type CSV';
            return false;
        }
    }

    /**
     * Method to validate that the marks entered for a result are less than the total 
     * marks for the exam. If validation fails, append an error to the $_errors array
     * @param array result array having keys (exam_id, user_id, marks)
     * @return boolean 
     */
    private function validate_marks($result) {
        $total_marks = $this->_exam_marks[$result['exam_id']];
        if ($result['marks'] > $total_marks) {
            $e = 'Marks entered for Student Id %d for %s are greater than total marks (%s)';
            $exam_name = Arr::get($this->_exams->as_array('id', 'name'), $result['exam_id']);
            $this->_errors['warning'] = sprintf($e, $result['user_id'], $exam_name, $total_marks);
            return false;
        }
        return true;
    }

    /**
     * Method to valdate that a student who;s marks are being uploaded is 
     * valid ie the result for this examgroup is applicable to him
     */
    private function validate_student($user_id) {
        if (!isset($this->_students[$user_id])) {
            $e = 'This result is not applicable for Student Id %d. Please recheck the csv file.';
            $this->_errors['warning'] = sprintf($e, $user_id);
            return false;
        }
        return true;
    }

    /*
     * Method to get the matrix which will be finally put into the csv
     * @param array $students keys = student_ids, values = Student Names
     * @param array $exams {keys = exam_ids, values = Exam Names}
     * @param array $results optional 
     *           eg. array(
     *                 'student_id' => array('exam_id' => marks)
     *               )
     */
    public static function matrix($students, $exams, $results=array()) {
        $matrix = array();
        // the first header line
        $matrix[0] = array_values($exams);
        array_unshift($matrix[0], "Student Id", "Student Name");
        // print_r($matrix[0]); exit;
        foreach ($students as $student_id=>$student_name) {
            $line = array(
                $student_id,
                $student_name                
            );
            foreach ($exams as $exam_id=>$exam_name) {
                if ($results && isset($results[$student_id])) {
                    $line[] = Arr::get($results[$student_id], $exam_id, '');
                } else {
                    $line[] = '';
                }
            }
            $matrix[] = $line;           
        }
        return $matrix;
    }
}