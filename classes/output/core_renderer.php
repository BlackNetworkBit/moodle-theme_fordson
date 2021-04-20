<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
namespace theme_fordson\output;

use context_course;
use core\session\manager;
use core_auth\output\login;
use core_completion\progress;
use custom_menu;
use custom_menu_item;
use html_writer;
use moodle_url;
use navigation_node;
use stdClass;
use theme_config;
use function preg_replace;

defined('MOODLE_INTERNAL') || die;
require_once($CFG->dirroot . "/course/renderer.php");

/**
 * Renderers to align Moodle's HTML with that expected by Bootstrap
 *
 * @package    theme_fordson
 * @copyright  2012 Bas Brands, www.basbrands.nl
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class core_renderer extends \theme_boost\output\core_renderer {
    protected static function timeaccesscompare($a, $b) {
        // Timeaccess is lastaccess entry and timestart an enrol entry.
        if ((!empty($a->timeaccess)) && (!empty($b->timeaccess))) {
            // Both last access.
            if ($a->timeaccess == $b->timeaccess) {
                return 0;
            }
            return ($a->timeaccess > $b->timeaccess) ? -1 : 1;
        } else if ((!empty($a->timestart)) && (!empty($b->timestart))) {
            // Both enrol.
            if ($a->timestart == $b->timestart) {
                return 0;
            }
            return ($a->timestart > $b->timestart) ? -1 : 1;
        }
        // Must be comparing an enrol with a last access.
        // -1 is to say that 'a' comes before 'b'.
        if (!empty($a->timestart)) {
            // The value 'a' is the enrol entry.
            return -1;
        }
        // The value 'b' must be the enrol entry.
        return 1;
    }

    /**
     * Wrapper for header elements.
     *
     * @return string HTML to display the main header.
     */
    public function headerbkglocation() {
        $theme = theme_config::load('fordson');
        $setting = $theme->settings->pagelayout;
        return $setting <= 4 ? true : false;
    }

    public function full_header() {
        global $COURSE, $course;
        $theme = theme_config::load('fordson');
        $this->pagelayout = $theme->settings->pagelayout;
        $header = new stdClass();
        if ($this->pagelayout <= 4) {
            $header->headerimagelocation = false;
        }
        if (!$this->page->theme->settings->coursemanagementtoggle) {
            $header->settingsmenu = $this->context_header_settings_menu();
        } else if (isset($COURSE->id) && $COURSE->id == 1) {
            $header->settingsmenu = $this->context_header_settings_menu();
        }
        $header->boostimage = $theme->settings->pagelayout == 5;
        $header->contextheader = html_writer::link(new moodle_url('/course/view.php', [
            'id' => $this->page->course->id
        ]),
            $this->context_header());
        $header->hasnavbar = empty($this->page->layout_options['nonavbar']);
        $header->navbar = $this->navbar();
        $header->pageheadingbutton = $this->page_heading_button();
        $header->courseheader = $this->course_header();
        $header->headerimage = $this->headerimage();
        $header->headeractions = $this->page->get_header_actions();

        if (theme_fordson_get_setting('jitsibuttontext') && $this->page->pagelayout == 'course') {
            $jitsibuttonurl = $theme->settings->jitsibuttonurl;
            $jitsibuttontext = $theme->settings->jitsibuttontext;
            $header->jitsi = '<a class="btn btn-primary" href=" ' . $jitsibuttonurl . '/' . $course->id . ' ' . $course->fullname .
                '" target="_blank"> <i class="fa fa-video-camera jitsivideoicon" aria-hidden="true">' .
                '</i><span class="jistibuttontext">' . $jitsibuttontext . ' </span></a>';
        }

        return $this->render_from_template('theme_fordson/header', $header);
    }

    public function headerimage() {
        global $CFG, $COURSE;
        // Get course overview files.
        if (empty($CFG->courseoverviewfileslimit)) {
            return '';
        }
        require_once($CFG->libdir . '/filestorage/file_storage.php');
        require_once($CFG->dirroot . '/course/lib.php');

        $fs = get_file_storage();
        $context = context_course::instance($COURSE->id);
        $files = $fs->get_area_files($context->id, 'course', 'overviewfiles', false, 'filename', false);
        if (count($files)) {
            $overviewfilesoptions = course_overviewfiles_options($COURSE->id);
            $acceptedtypes = $overviewfilesoptions['accepted_types'];
            if ($acceptedtypes !== '*') {
                // Filter only files with allowed extensions.
                require_once($CFG->libdir . '/filelib.php');
                foreach ($files as $key => $file) {
                    if (!file_extension_in_typegroup($file->get_filename(), $acceptedtypes)) {
                        unset($files[$key]);
                    }
                }
            }
            if (count($files) > $CFG->courseoverviewfileslimit) {
                // Return no more than $CFG->courseoverviewfileslimit files.
                $files = array_slice($files, 0, $CFG->courseoverviewfileslimit, true);
            }
        }
        // Get course overview files as images - set $courseimage.
        // The loop means that the LAST stored image will be the one displayed if >1 image file.
        $courseimage = '';
        foreach ($files as $file) {
            $isimage = $file->is_valid_image();
            if ($isimage) {
                $courseimage = file_encode_url("$CFG->wwwroot/pluginfile.php",
                    '/' . $file->get_contextid() . '/' . $file->get_component() . '/' .
                    $file->get_filearea() . $file->get_filepath() . $file->get_filename(),
                    !$isimage);
            }
        }
        $headerbg = $this->page->theme->setting_file_url('headerdefaultimage', 'headerdefaultimage');
        $headerbgimgurl = $this->page->theme->setting_file_url('headerdefaultimage', 'headerdefaultimage', true);
        $defaultimgurl = $this->image_url('headerbg', 'theme');
        // Create html for header.
        $html = html_writer::start_div('headerbkg');
        // If course image display it in separate div to allow css styling of inline style.
        if (theme_fordson_get_setting('showcourseheaderimage') && $courseimage) {
            $html .= html_writer::start_div('withimage',
                ['style' => 'background-image: url("' . $courseimage . '"); background-size: cover; background-position:center;
                width: 100%; height: 100%;']);
            $html .= html_writer::end_div(); // End withimage inline style div.

        } else if (theme_fordson_get_setting('showcourseheaderimage') && !$courseimage && isset($headerbg)) {
            $html .= html_writer::start_div('customimage',
                ['style' => 'background-image: url("' . $headerbgimgurl . '"); background-size: cover; background-position:center;
                width: 100%; height: 100%;']);
            $html .= html_writer::end_div(); // End withoutimage inline style div.

        } else if ($courseimage && isset($headerbg) && !theme_fordson_get_setting('showcourseheaderimage')) {
            $html .= html_writer::start_div('customimage',
                ['style' => 'background-image: url("' . $headerbgimgurl . '"); background-size: cover; background-position:center;
                width: 100%; height: 100%;']);
            $html .= html_writer::end_div(); // End withoutimage inline style div.

        } else if (!$courseimage && isset($headerbg) && !theme_fordson_get_setting('showcourseheaderimage')) {
            $html .= html_writer::start_div('customimage',
                ['style' => 'background-image: url("' . $headerbgimgurl . '"); background-size: cover; background-position:center;
                width: 100%; height: 100%;']);
            $html .= html_writer::end_div(); // End withoutimage inline style div.

        } else {
            $html .= html_writer::start_div('default',
                ['style' => 'background-image: url("' . $defaultimgurl . '"); background-size: cover; background-position:center;
                width: 100%; height: 100%;']);
            $html .= html_writer::end_div(); // End default inline style div.

        }
        $html .= html_writer::end_div();
        return $html;
    }

    public function image_url($imagename, $component = 'moodle') {
        // Strip -24, -64, -256  etc from the end of filetype icons so we
        // only need to provide one SVG, see MDL-47082.
        $imagename = preg_replace('/-\d\d\d?$/', '', $imagename);
        return $this->page->theme->image_url($imagename, $component);
    }

    public function get_generated_image_for_id($id) {
        // See if user uploaded a custom header background to the theme.
        $headerbg = $this->page->theme->setting_file_url('headerdefaultimage', 'headerdefaultimage');
        if (isset($headerbg)) {
            return $headerbg;
        } else {
            // Use the default theme image when no course image is detected.
            return $this->image_url('noimg', 'theme')->out();
        }
    }

    public function edit_button(moodle_url $url) {
        return '';
    }

    public function edit_button_fhs() {
        global $SITE, $USER, $CFG, $COURSE;
        if (!$this->page->user_allowed_editing() || $COURSE->id <= 1) {
            return '';
        }
        if ($this->page->pagelayout == 'course') {
            $url = new moodle_url($this->page->url);
            $url->param('sesskey', sesskey());
            if ($this->page->user_is_editing()) {
                $url->param('edit', 'off');
                $btn = 'btn-danger editingbutton';
                $title = get_string('editoff', 'theme_fordson');
                $icon = 'fa-power-off';
            } else {
                $url->param('edit', 'on');
                $btn = 'btn-success editingbutton';
                $title = get_string('editon', 'theme_fordson');
                $icon = 'fa-edit';
            }
            return html_writer::tag('a',
                html_writer::start_tag('i',
                    [
                        'class' => $icon . ' fa fa-fw'
                    ]) . html_writer::end_tag('i'),
                [
                    'href' => $url,
                    'class' => 'btn edit-btn ' . $btn,
                    'data-tooltip' => "tooltip",
                    'data-placement' => "bottom",
                    'title' => $title,
                ]);
            return $this;
        }
    }

    /*
     * This renders the bootstrap top menu.
     *
     * This renderer is needed to enable the Bootstrap style navigation.
    */

    public function fordson_custom_menu() {
        global $CFG, $COURSE;
        $context = $this->page->context;
        $menu = new custom_menu();
        $hasdisplaymycourses = (empty($this->page->theme->settings->displaymycourses)) ? false :
            $this->page->theme->settings->displaymycourses;
        if (isloggedin() && !isguestuser() && $hasdisplaymycourses) {
            $mycoursetitle = $this->page->theme->settings->mycoursetitle;
            if ($mycoursetitle == 'module') {
                $branchtitle = get_string('mymodules', 'theme_fordson');
                $thisbranchtitle = get_string('thismymodules', 'theme_fordson');
                $homebranchtitle = get_string('homemymodules', 'theme_fordson');
            } else if ($mycoursetitle == 'unit') {
                $branchtitle = get_string('myunits', 'theme_fordson');
                $thisbranchtitle = get_string('thismyunits', 'theme_fordson');
                $homebranchtitle = get_string('homemyunits', 'theme_fordson');
            } else if ($mycoursetitle == 'class') {
                $branchtitle = get_string('myclasses', 'theme_fordson');
                $thisbranchtitle = get_string('thismyclasses', 'theme_fordson');
                $homebranchtitle = get_string('homemyclasses', 'theme_fordson');
            } else if ($mycoursetitle == 'training') {
                $branchtitle = get_string('mytraining', 'theme_fordson');
                $thisbranchtitle = get_string('thismytraining', 'theme_fordson');
                $homebranchtitle = get_string('homemytraining', 'theme_fordson');
            } else if ($mycoursetitle == 'pd') {
                $branchtitle = get_string('myprofessionaldevelopment', 'theme_fordson');
                $thisbranchtitle = get_string('thismyprofessionaldevelopment', 'theme_fordson');
                $homebranchtitle = get_string('homemyprofessionaldevelopment', 'theme_fordson');
            } else if ($mycoursetitle == 'cred') {
                $branchtitle = get_string('mycred', 'theme_fordson');
                $thisbranchtitle = get_string('thismycred', 'theme_fordson');
                $homebranchtitle = get_string('homemycred', 'theme_fordson');
            } else if ($mycoursetitle == 'plan') {
                $branchtitle = get_string('myplans', 'theme_fordson');
                $thisbranchtitle = get_string('thismyplans', 'theme_fordson');
                $homebranchtitle = get_string('homemyplans', 'theme_fordson');
            } else if ($mycoursetitle == 'comp') {
                $branchtitle = get_string('mycomp', 'theme_fordson');
                $thisbranchtitle = get_string('thismycomp', 'theme_fordson');
                $homebranchtitle = get_string('homemycomp', 'theme_fordson');
            } else if ($mycoursetitle == 'program') {
                $branchtitle = get_string('myprograms', 'theme_fordson');
                $thisbranchtitle = get_string('thismyprograms', 'theme_fordson');
                $homebranchtitle = get_string('homemyprograms', 'theme_fordson');
            } else if ($mycoursetitle == 'lecture') {
                $branchtitle = get_string('mylectures', 'theme_fordson');
                $thisbranchtitle = get_string('thismylectures', 'theme_fordson');
                $homebranchtitle = get_string('homemylectures', 'theme_fordson');
            } else if ($mycoursetitle == 'lesson') {
                $branchtitle = get_string('mylessons', 'theme_fordson');
                $thisbranchtitle = get_string('thismylessons', 'theme_fordson');
                $homebranchtitle = get_string('homemylessons', 'theme_fordson');
            } else {
                $branchtitle = get_string('mycourses', 'theme_fordson');
                $thisbranchtitle = get_string('thismycourses', 'theme_fordson');
                $homebranchtitle = get_string('homemycourses', 'theme_fordson');
            }

            $branchlabel = $branchtitle;
            $branchurl = new moodle_url('/my/index.php');
            $branchsort = 10000;
            $branch = $menu->add($branchlabel, $branchurl, $branchtitle, $branchsort);
            $dashlabel = get_string('mymoodle', 'my');
            $dashurl = new moodle_url("/my");
            $dashtitle = $dashlabel;
            $branch->add($dashlabel, $dashurl, $dashtitle);

            if ($courses = enrol_get_my_courses(null, 'fullname ASC')) {
                if (theme_fordson_get_setting('frontpagemycoursessorting')) {
                    $courses = enrol_get_my_courses(null, 'sortorder ASC');
                    $nomycourses = '<div class="alert alert-info alert-block">' . get_string('nomycourses',
                            'theme_fordson') . '</div>';
                    if ($courses) {
                        // We have something to work with.  Get the last accessed information for the user and populate.
                        global $DB, $USER;
                        $lastaccess = $DB->get_records('user_lastaccess',
                            ['userid' => $USER->id],
                            '',
                            'courseid, timeaccess');
                        if ($lastaccess) {
                            foreach ($courses as $course) {
                                if (!empty($lastaccess[$course->id])) {
                                    $course->timeaccess = $lastaccess[$course->id]->timeaccess;
                                }
                            }
                        }
                        // Determine if we need to query the enrolment and user enrolment tables.
                        $enrolquery = false;
                        foreach ($courses as $course) {
                            if (empty($course->timeaccess)) {
                                $enrolquery = true;
                                break;
                            }
                        }
                        if ($enrolquery) {
                            // We do.
                            $params = [
                                'userid' => $USER->id
                            ];
                            $sql = "SELECT ue.id, e.courseid, ue.timestart
                            FROM {enrol} e
                            JOIN {user_enrolments} ue ON (ue.enrolid = e.id AND ue.userid = :userid)";
                            $enrolments = $DB->get_records_sql($sql, $params, 0, 0);
                            if ($enrolments) {
                                // Sort out any multiple enrolments on the same course.
                                $userenrolments = [];
                                foreach ($enrolments as $enrolment) {
                                    if (!empty($userenrolments[$enrolment->courseid])) {
                                        if ($userenrolments[$enrolment->courseid] < $enrolment->timestart) {
                                            // Replace.
                                            $userenrolments[$enrolment->courseid] = $enrolment->timestart;
                                        }
                                    } else {
                                        $userenrolments[$enrolment->courseid] = $enrolment->timestart;
                                    }
                                }
                                // We don't need to worry about time end as our course list,
                                // will be valid for the user from above.
                                foreach ($courses as $course) {
                                    if (empty($course->timeaccess)) {
                                        $course->timestart = $userenrolments[$course->id];
                                    }
                                }
                            }
                        }
                        uasort($courses, [$this, 'timeaccesscompare']);
                    } else {
                        return $nomycourses;
                    }
                    $sortorder = $lastaccess;
                }

                $numcourses = 0;
                $mycoursescatsubmenu = $this->page->theme->settings->mycoursescatsubmenu;
                $hasdisplayhiddenmycourses = $this->page->theme->settings->displayhiddenmycourses;
                if ($courses) {
                    $mycoursesmax = $this->page->theme->settings->mycoursesmax;
                    if (!$mycoursesmax) {
                        $mycoursesmax = PHP_INT_MAX;
                    }
                    if ($mycoursescatsubmenu) {
                        $coursecats = [];
                        $mycoursescatsubmenucatsnumcourses = [];
                        $toplistc = false;

                        $categorieslist = $this->get_categories_list();
                        foreach ($categorieslist as $category) {
                            if (empty($toplistc[$category->id])) {
                                $toplistc[$category->id] = new \stdClass;
                                if (!empty($category->parents)) {
                                    // Sub-category and the last entry in the array is the top.
                                    $toplistc[$category->id]->topid = $category->parents[(count($category->parents) - 1)];
                                } else {
                                    // We are a top level category.
                                    $toplistc[$category->id]->topid = $category->id;
                                    $toplistc[$category->id]->name = $categorieslist[$category->id]->name;
                                }
                            }
                        }
                    }
                }

                foreach ($courses as $course) {
                    if ($course->visible) {
                        if (!$mycoursescatsubmenu) {
                            if ($this->custom_menu_courses_add_course($branch, $course, $hasdisplayhiddenmycourses)) {
                                $numcourses += 1;
                            }
                            if ($numcourses == $mycoursesmax) {
                                break;
                            }
                        } else {
                            if (empty($coursecats[$toplistc[$course->category]->topid])) {
                                $cattext = format_string($toplistc[$toplistc[$course->category]->topid]->name);
                                $caticon = 'folder-open';
                                $catlabel = html_writer::tag('span',
                                    $this->getfontawesomemarkup($caticon) . html_writer::tag('span', ' ' . $cattext));
                                $coursecats[$toplistc[$course->category]->topid] = $branch->add($catlabel,
                                    $this->page->url,
                                    $cattext);
                                $mycoursescatsubmenucatsnumcourses[$toplistc[$course->category]->topid] = 0;
                            }
                            if ($mycoursescatsubmenucatsnumcourses[$toplistc[$course->category]->topid] < $mycoursesmax) {
                                // Only add if we are within the course limit.
                                if ($this->custom_menu_courses_add_course($coursecats[$toplistc[$course->category]->topid],
                                    $course,
                                    $hasdisplayhiddenmycourses)) {
                                    $mycoursescatsubmenucatsnumcourses[$toplistc[$course->category]->topid] += 1;
                                }
                            }
                        }
                    }
                }
                if ($mycoursescatsubmenu) {
                    // Tally.
                    foreach ($mycoursescatsubmenucatsnumcourses as $catcoursenum) {
                        $numcourses += $catcoursenum;
                    }
                }
            } else {
                $noenrolments = get_string('noenrolments', 'theme_fordson');
                $branch->add('<em>' . $noenrolments . '</em>', new moodle_url('/'), $noenrolments);
            }

            $hasdisplaythiscourse = (empty($this->page->theme->settings->displaythiscourse)) ? false :
                $this->page->theme->settings->displaythiscourse;
            $sections = $this->generate_sections_and_activities($COURSE);
            if ($sections && $COURSE->id > 1 && $hasdisplaythiscourse) {
                $branchlabel = $thisbranchtitle;
                $branch = $menu->add($branchlabel, $branchurl, $branchtitle, $branchsort);
                $course = course_get_format($COURSE)->get_course();
                $coursehomelabel = $homebranchtitle;
                $coursehomeurl = new moodle_url('/course/view.php?', [
                    'id' => $this->page->course->id
                ]);
                $coursehometitle = $coursehomelabel;
                $branch->add($coursehomelabel, $coursehomeurl, $coursehometitle);
                $callabel = get_string('calendar', 'calendar');
                $calurl = new moodle_url('/calendar/view.php?view=month', [
                    'course' => $this->page->course->id
                ]);
                $caltitle = $callabel;
                $branch->add($callabel, $calurl, $caltitle);
                $participantlabel = get_string('participants', 'moodle');
                $participanturl = new moodle_url('/user/index.php', [
                    'id' => $this->page->course->id
                ]);
                $participanttitle = $participantlabel;
                $branch->add($participantlabel, $participanturl, $participanttitle);
                if ($CFG->enablebadges == 1) {
                    $badgelabel = get_string('badges', 'badges');
                    $badgeurl = new moodle_url('/badges/view.php?type=2', [
                        'id' => $this->page->course->id
                    ]);
                    $badgetitle = $badgelabel;
                    $branch->add($badgelabel, $badgeurl, $badgetitle);
                }
                if (get_config('core_competency', 'enabled')) {
                    $complabel = get_string('competencies', 'competency');
                    $compurl = new moodle_url('/admin/tool/lp/coursecompetencies.php', [
                        'courseid' => $this->page->course->id
                    ]);
                    $comptitle = $complabel;
                    $branch->add($complabel, $compurl, $comptitle);
                }
                foreach ($sections[0] as $sectionid => $section) {
                    $sectionname = get_section_name($COURSE, $section);
                    if (isset($course->coursedisplay) && $course->coursedisplay == COURSE_DISPLAY_MULTIPAGE) {
                        $sectionurl = '/course/view.php?id=' . $COURSE->id . '&section=' . $sectionid;
                    } else {
                        $sectionurl = '/course/view.php?id=' . $COURSE->id . '#section-' . $sectionid;
                    }
                    $branch->add(format_string($sectionname), new moodle_url($sectionurl), format_string($sectionname));
                }
            }
        }
        return $this->render_custom_menu($menu);;
    }

    /**
     * Renders the custom_menu
     *
     * @param custom_menu $menu
     * @return string $content
     * @throws \moodle_exception
     */
    protected function render_custom_menu(custom_menu $menu) {
        if ($this->page->theme->settings->mycoursescatsubmenu) {
            $obj = new stdClass();
            $obj->menuitems = [];
            foreach ($menu->get_children() as $item) {
                $context = $item->export_for_template($this);
                $obj->menuitems[] = $context;
            }

            foreach ($obj->menuitems as $menuitem) {
                foreach ($menuitem->children as $child) {
                    $child->key = uniqid();
                }
            }
            return $this->render_from_template('theme_fordson/custom_menu_item', $obj);
        } else {
            $content = '';
            foreach ($menu->get_children() as $item) {
                $context = $item->export_for_template($this);
                $content .= $this->render_from_template('core/custom_menu_item', $context);
            }
            return $content;
        }

    }

    /**
     * Renders menu items for the custom_menu
     *
     * @param custom_menu_item $branch                    Menu branch to add the course to.
     * @param stdClass         $course                    Course to use.
     * @param boolean          $hasdisplayhiddenmycourses Display hidden courses.
     * @return boolean $courseadded if the course was added to the branch.
     */
    protected function custom_menu_courses_add_course($branch, $course, $hasdisplayhiddenmycourses) {
        $courseadded = false;
        if ($course->visible) {
            $branchtitle = format_string($course->shortname);
            $branchurl = new moodle_url('/course/view.php', ['id' => $course->id]);
            $enrolledclass = '';
            if (!empty($course->timestart)) {
                $enrolledclass .= ' class="onlyenrolled"';
            }
            $branchlabel = '<span' . $enrolledclass . '>' .
                $this->getfontawesomemarkup('graduation-cap') . format_string($course->fullname) . '</span>';
            $branch->add($branchlabel, $branchurl, $branchtitle);
            $courseadded = true;
        } else if (has_capability('moodle/course:viewhiddencourses',
                context_course::instance($course->id)) && $hasdisplayhiddenmycourses) {
            $branchtitle = format_string($course->shortname);
            $enrolledclass = '';
            if (!empty($course->timestart)) {
                $enrolledclass .= ' onlyenrolled';
            }
            $branchlabel = '<span class="dimmed_text' . $enrolledclass . '">' . $this->getfontawesomemarkup('eye-slash') .
                format_string($course->fullname) . '</span>';
            $branchurl = new moodle_url('/course/view.php', ['id' => $course->id]);
            $branch->add($branchlabel, $branchurl, $branchtitle);
            $courseadded = true;
        }
        return $courseadded;
    }

    protected function getfontawesomemarkup($theicon, $classes = [], $attributes = [], $content = '') {
        $classes[] = 'fa fa-' . $theicon;
        $attributes['aria-hidden'] = 'true';
        $attributes['class'] = implode(' ', $classes);
        return html_writer::tag('span', $content, $attributes);
    }

    protected function get_categories_list() {
        static $catlist = null;
        if (empty($catlist)) {
            global $DB;
            $catlist = $DB->get_records('course_categories', null, 'sortorder', 'id, name, depth, path');

            foreach ($catlist as $category) {
                $category->parents = [];
                if ($category->depth > 1) {
                    $path = preg_split('|/|', $category->path, -1, PREG_SPLIT_NO_EMPTY);
                    $category->namechunks = [];
                    foreach ($path as $parentid) {
                        $category->namechunks[] = $catlist[$parentid]->name;
                        $category->parents[] = $parentid;
                    }
                    $category->parents = array_reverse($category->parents);
                } else {
                    $category->namechunks = [$category->name];
                }
            }
        }

        return $catlist;
    }

    /**
     * Generates an array of sections and an array of activities for the given course.
     *
     * This method uses the cache to improve performance and avoid the get_fast_modinfo call
     *
     * @param stdClass $course
     * @return array Array($sections, $activities)
     */
    protected function generate_sections_and_activities(stdClass $course) {
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');
        $modinfo = get_fast_modinfo($course);
        $sections = $modinfo->get_section_info_all();
        // For course formats using 'numsections' trim the sections list.
        $courseformatoptions = course_get_format($course)->get_format_options();
        if (isset($courseformatoptions['numsections'])) {
            $sections = array_slice($sections, 0, $courseformatoptions['numsections'] + 1, true);
        }
        $activities = [];
        foreach ($sections as $key => $section) {
            // Clone and unset summary to prevent $SESSION bloat (MDL-31802).
            $sections[$key] = clone($section);
            unset($sections[$key]->summary);
            $sections[$key]->hasactivites = false;
            if (!array_key_exists($section->section, $modinfo->sections)) {
                continue;
            }
            foreach ($modinfo->sections[$section->section] as $cmid) {
                $cm = $modinfo->cms[$cmid];
                $activity = new stdClass;
                $activity->id = $cm->id;
                $activity->course = $course->id;
                $activity->section = $section->section;
                $activity->name = $cm->name;
                $activity->icon = $cm->icon;
                $activity->iconcomponent = $cm->iconcomponent;
                $activity->hidden = (!$cm->visible);
                $activity->modname = $cm->modname;
                $activity->nodetype = navigation_node::NODETYPE_LEAF;
                $activity->onclick = $cm->onclick;
                $url = $cm->url;
                if (!$url) {
                    $activity->url = null;
                    $activity->display = false;
                } else {
                    $activity->url = $url->out();
                    $activity->display = $cm->is_visible_on_course_page() ? true : false;
                }
                $activities[$cmid] = $activity;
                if ($activity->display) {
                    $sections[$key]->hasactivites = true;
                }
            }
        }
        return [
            $sections,
            $activities
        ];
    }

    public function social_icons() {
        $hasfacebook = (empty($this->page->theme->settings->facebook)) ? false : $this->page->theme->settings->facebook;
        $hastwitter = (empty($this->page->theme->settings->twitter)) ? false : $this->page->theme->settings->twitter;
        $hasgoogleplus = (empty($this->page->theme->settings->googleplus)) ? false : $this->page->theme->settings->googleplus;
        $haslinkedin = (empty($this->page->theme->settings->linkedin)) ? false : $this->page->theme->settings->linkedin;
        $hasyoutube = (empty($this->page->theme->settings->youtube)) ? false : $this->page->theme->settings->youtube;
        $hasflickr = (empty($this->page->theme->settings->flickr)) ? false : $this->page->theme->settings->flickr;
        $hasvk = (empty($this->page->theme->settings->vk)) ? false : $this->page->theme->settings->vk;
        $haspinterest = (empty($this->page->theme->settings->pinterest)) ? false : $this->page->theme->settings->pinterest;
        $hasinstagram = (empty($this->page->theme->settings->instagram)) ? false : $this->page->theme->settings->instagram;
        $hasskype = (empty($this->page->theme->settings->skype)) ? false : $this->page->theme->settings->skype;
        $haswebsite = (empty($this->page->theme->settings->website)) ? false : $this->page->theme->settings->website;
        $hasblog = (empty($this->page->theme->settings->blog)) ? false : $this->page->theme->settings->blog;
        $hasvimeo = (empty($this->page->theme->settings->vimeo)) ? false : $this->page->theme->settings->vimeo;
        $hastumblr = (empty($this->page->theme->settings->tumblr)) ? false : $this->page->theme->settings->tumblr;
        $hassocial1 = (empty($this->page->theme->settings->social1)) ? false : $this->page->theme->settings->social1;
        $social1icon = (empty($this->page->theme->settings->socialicon1)) ? 'globe' : $this->page->theme->settings->socialicon1;
        $hassocial2 = (empty($this->page->theme->settings->social2)) ? false : $this->page->theme->settings->social2;
        $social2icon = (empty($this->page->theme->settings->socialicon2)) ? 'globe' : $this->page->theme->settings->socialicon2;
        $hassocial3 = (empty($this->page->theme->settings->social3)) ? false : $this->page->theme->settings->social3;
        $social3icon = (empty($this->page->theme->settings->socialicon3)) ? 'globe' : $this->page->theme->settings->socialicon3;
        $socialcontext = [
            // If any of the above social networks are true, sets this to true.
            'hassocialnetworks' => ($hasfacebook || $hastwitter || $hasgoogleplus || $hasflickr || $hasinstagram
                || $hasvk || $haslinkedin || $haspinterest || $hasskype || $haslinkedin || $haswebsite ||
                $hasyoutube || $hasblog || $hasvimeo || $hastumblr || $hassocial1 || $hassocial2 || $hassocial3) ?
                true : false, 'socialicons' => [
                [
                    'haslink' => $hasfacebook,
                    'linkicon' => 'facebook'
                ],
                [
                    'haslink' => $hastwitter,
                    'linkicon' => 'twitter'
                ],
                [
                    'haslink' => $hasgoogleplus,
                    'linkicon' => 'google-plus'
                ],
                [
                    'haslink' => $haslinkedin,
                    'linkicon' => 'linkedin'
                ],
                [
                    'haslink' => $hasyoutube,
                    'linkicon' => 'youtube'
                ],
                [
                    'haslink' => $hasflickr,
                    'linkicon' => 'flickr'
                ],
                [
                    'haslink' => $hasvk,
                    'linkicon' => 'vk'
                ],
                [
                    'haslink' => $haspinterest,
                    'linkicon' => 'pinterest'
                ],
                [
                    'haslink' => $hasinstagram,
                    'linkicon' => 'instagram'
                ],
                [
                    'haslink' => $hasskype,
                    'linkicon' => 'skype'
                ],
                [
                    'haslink' => $haswebsite,
                    'linkicon' => 'globe'
                ],
                [
                    'haslink' => $hasblog,
                    'linkicon' => 'bookmark'
                ],
                [
                    'haslink' => $hasvimeo,
                    'linkicon' => 'vimeo-square'
                ],
                [
                    'haslink' => $hastumblr,
                    'linkicon' => 'tumblr'
                ],
                [
                    'haslink' => $hassocial1,
                    'linkicon' => $social1icon
                ],
                [
                    'haslink' => $hassocial2,
                    'linkicon' => $social2icon
                ],
                [
                    'haslink' => $hassocial3,
                    'linkicon' => $social3icon
                ],
            ]];
        return $this->render_from_template('theme_fordson/socialicons', $socialcontext);
    }

    public function fp_wonderbox() {
        $context = $this->page->context;
        $hascreateicon = (empty($this->page->theme->settings->createicon && isloggedin() &&
            has_capability('moodle/course:create', $context))) ? false : $this->page->theme->settings->createicon;
        $createbuttonurl = (empty($this->page->theme->settings->createbuttonurl)) ? false :
            $this->page->theme->settings->createbuttonurl;
        $createbuttontext = (empty($this->page->theme->settings->createbuttontext)) ? false :
            format_string($this->page->theme->settings->createbuttontext);
        $hasslideicon = (empty($this->page->theme->settings->slideicon && isloggedin() && !isguestuser())) ? false :
            $this->page->theme->settings->slideicon;
        $slideiconbuttonurl = 'data-toggle="collapse" data-target="#collapseExample';
        $slideiconbuttontext = (empty($this->page->theme->settings->slideiconbuttontext)) ? false :
            format_string($this->page->theme->settings->slideiconbuttontext);
        $hasnav1icon = (empty($this->page->theme->settings->nav1icon && isloggedin() && !isguestuser())) ? false :
            $this->page->theme->settings->nav1icon;
        $hasnav2icon = (empty($this->page->theme->settings->nav2icon && isloggedin() && !isguestuser())) ? false :
            $this->page->theme->settings->nav2icon;
        $hasnav3icon = (empty($this->page->theme->settings->nav3icon && isloggedin() && !isguestuser())) ? false :
            $this->page->theme->settings->nav3icon;
        $hasnav4icon = (empty($this->page->theme->settings->nav4icon && isloggedin() && !isguestuser())) ? false :
            $this->page->theme->settings->nav4icon;
        $hasnav5icon = (empty($this->page->theme->settings->nav5icon && isloggedin() && !isguestuser())) ? false :
            $this->page->theme->settings->nav5icon;
        $hasnav6icon = (empty($this->page->theme->settings->nav6icon && isloggedin() && !isguestuser())) ? false :
            $this->page->theme->settings->nav6icon;
        $hasnav7icon = (empty($this->page->theme->settings->nav7icon && isloggedin() && !isguestuser())) ? false :
            $this->page->theme->settings->nav7icon;
        $hasnav8icon = (empty($this->page->theme->settings->nav8icon && isloggedin() && !isguestuser())) ? false :
            $this->page->theme->settings->nav8icon;
        $nav1buttonurl = (empty($this->page->theme->settings->nav1buttonurl)) ? false :
            $this->page->theme->settings->nav1buttonurl;
        $nav2buttonurl = (empty($this->page->theme->settings->nav2buttonurl)) ? false :
            $this->page->theme->settings->nav2buttonurl;
        $nav3buttonurl = (empty($this->page->theme->settings->nav3buttonurl)) ? false :
            $this->page->theme->settings->nav3buttonurl;
        $nav4buttonurl = (empty($this->page->theme->settings->nav4buttonurl)) ? false :
            $this->page->theme->settings->nav4buttonurl;
        $nav5buttonurl = (empty($this->page->theme->settings->nav5buttonurl)) ? false :
            $this->page->theme->settings->nav5buttonurl;
        $nav6buttonurl = (empty($this->page->theme->settings->nav6buttonurl)) ? false :
            $this->page->theme->settings->nav6buttonurl;
        $nav7buttonurl = (empty($this->page->theme->settings->nav7buttonurl)) ? false :
            $this->page->theme->settings->nav7buttonurl;
        $nav8buttonurl = (empty($this->page->theme->settings->nav8buttonurl)) ? false :
            $this->page->theme->settings->nav8buttonurl;
        $nav1buttontext = (empty($this->page->theme->settings->nav1buttontext)) ? false :
            format_string($this->page->theme->settings->nav1buttontext);
        $nav2buttontext = (empty($this->page->theme->settings->nav2buttontext)) ? false :
            format_string($this->page->theme->settings->nav2buttontext);
        $nav3buttontext = (empty($this->page->theme->settings->nav3buttontext)) ? false :
            format_string($this->page->theme->settings->nav3buttontext);
        $nav4buttontext = (empty($this->page->theme->settings->nav4buttontext)) ? false :
            format_string($this->page->theme->settings->nav4buttontext);
        $nav5buttontext = (empty($this->page->theme->settings->nav5buttontext)) ? false :
            format_string($this->page->theme->settings->nav5buttontext);
        $nav6buttontext = (empty($this->page->theme->settings->nav6buttontext)) ? false :
            format_string($this->page->theme->settings->nav6buttontext);
        $nav7buttontext = (empty($this->page->theme->settings->nav7buttontext)) ? false :
            format_string($this->page->theme->settings->nav7buttontext);
        $nav8buttontext = (empty($this->page->theme->settings->nav8buttontext)) ? false :
            format_string($this->page->theme->settings->nav8buttontext);
        $nav1target = (empty($this->page->theme->settings->nav1target)) ? false : $this->page->theme->settings->nav1target;
        $nav2target = (empty($this->page->theme->settings->nav2target)) ? false : $this->page->theme->settings->nav2target;
        $nav3target = (empty($this->page->theme->settings->nav3target)) ? false : $this->page->theme->settings->nav3target;
        $nav4target = (empty($this->page->theme->settings->nav4target)) ? false : $this->page->theme->settings->nav4target;
        $nav5target = (empty($this->page->theme->settings->nav5target)) ? false : $this->page->theme->settings->nav5target;
        $nav6target = (empty($this->page->theme->settings->nav6target)) ? false : $this->page->theme->settings->nav6target;
        $nav7target = (empty($this->page->theme->settings->nav7target)) ? false : $this->page->theme->settings->nav7target;
        $nav8target = (empty($this->page->theme->settings->nav8target)) ? false : $this->page->theme->settings->nav8target;
        $fptextbox = (empty($this->page->theme->settings->fptextbox && isloggedin())) ? false :
            format_text($this->page->theme->settings->fptextbox,
                FORMAT_HTML,
                [
                    'noclean' => true
                ]);
        $fptextboxlogout = (empty($this->page->theme->settings->fptextboxlogout && !isloggedin())) ? false :
            format_text($this->page->theme->settings->fptextboxlogout,
                FORMAT_HTML,
                [
                    'noclean' => true
                ]);
        $slidetextbox = (empty($this->page->theme->settings->slidetextbox && isloggedin())) ? false :
            format_text($this->page->theme->settings->slidetextbox,
                FORMAT_HTML,
                [
                    'noclean' => true
                ]);
        $alertbox = (empty($this->page->theme->settings->alertbox)) ? false :
            format_text($this->page->theme->settings->alertbox,
                FORMAT_HTML,
                [
                    'noclean' => true
                ]);

        $hasmarketing1 = (empty($this->page->theme->settings->marketing1 &&
            $this->page->theme->settings->togglemarketing == 1)) ? false :
            format_string($this->page->theme->settings->marketing1);
        $marketing1content = (empty($this->page->theme->settings->marketing1content)) ? false :
            format_text($this->page->theme->settings->marketing1content);
        $marketing1buttontext = (empty($this->page->theme->settings->marketing1buttontext)) ? false :
            format_string($this->page->theme->settings->marketing1buttontext);
        $marketing1buttonurl = (empty($this->page->theme->settings->marketing1buttonurl)) ? false :
            $this->page->theme->settings->marketing1buttonurl;
        $marketing1target = (empty($this->page->theme->settings->marketing1target)) ? false :
            $this->page->theme->settings->marketing1target;
        $marketing1image = (empty($this->page->theme->settings->marketing1image)) ? false : 'marketing1image';

        $hasmarketing2 = (empty($this->page->theme->settings->marketing2
            && $this->page->theme->settings->togglemarketing == 1)) ? false :
            format_string($this->page->theme->settings->marketing2);
        $marketing2content = (empty($this->page->theme->settings->marketing2content)) ? false :
            format_text($this->page->theme->settings->marketing2content);
        $marketing2buttontext = (empty($this->page->theme->settings->marketing2buttontext)) ? false :
            format_string($this->page->theme->settings->marketing2buttontext);
        $marketing2buttonurl = (empty($this->page->theme->settings->marketing2buttonurl)) ? false :
            $this->page->theme->settings->marketing2buttonurl;
        $marketing2target = (empty($this->page->theme->settings->marketing2target)) ? false :
            $this->page->theme->settings->marketing2target;
        $marketing2image = (empty($this->page->theme->settings->marketing2image)) ? false : 'marketing2image';

        $hasmarketing3 = (empty($this->page->theme->settings->marketing3
            && $this->page->theme->settings->togglemarketing == 1)) ? false :
            format_string($this->page->theme->settings->marketing3);
        $marketing3content = (empty($this->page->theme->settings->marketing3content)) ? false :
            format_text($this->page->theme->settings->marketing3content);
        $marketing3buttontext = (empty($this->page->theme->settings->marketing3buttontext)) ? false :
            format_string($this->page->theme->settings->marketing3buttontext);
        $marketing3buttonurl = (empty($this->page->theme->settings->marketing3buttonurl)) ? false :
            $this->page->theme->settings->marketing3buttonurl;
        $marketing3target = (empty($this->page->theme->settings->marketing3target)) ? false :
            $this->page->theme->settings->marketing3target;
        $marketing3image = (empty($this->page->theme->settings->marketing3image)) ? false : 'marketing3image';

        $hasmarketing4 = (empty($this->page->theme->settings->marketing4
            && $this->page->theme->settings->togglemarketing == 1)) ? false :
            format_string($this->page->theme->settings->marketing4);
        $marketing4content = (empty($this->page->theme->settings->marketing4content)) ? false :
            format_text($this->page->theme->settings->marketing4content);
        $marketing4buttontext = (empty($this->page->theme->settings->marketing4buttontext)) ? false :
            format_string($this->page->theme->settings->marketing4buttontext);
        $marketing4buttonurl = (empty($this->page->theme->settings->marketing4buttonurl)) ? false :
            $this->page->theme->settings->marketing4buttonurl;
        $marketing4target = (empty($this->page->theme->settings->marketing4target)) ? false :
            $this->page->theme->settings->marketing4target;
        $marketing4image = (empty($this->page->theme->settings->marketing4image)) ? false : 'marketing4image';

        $hasmarketing5 = (empty($this->page->theme->settings->marketing5
            && $this->page->theme->settings->togglemarketing == 1)) ? false :
            format_string($this->page->theme->settings->marketing5);
        $marketing5content = (empty($this->page->theme->settings->marketing5content)) ? false :
            format_text($this->page->theme->settings->marketing5content);
        $marketing5buttontext = (empty($this->page->theme->settings->marketing5buttontext)) ? false :
            format_string($this->page->theme->settings->marketing5buttontext);
        $marketing5buttonurl = (empty($this->page->theme->settings->marketing5buttonurl)) ? false :
            $this->page->theme->settings->marketing5buttonurl;
        $marketing5target = (empty($this->page->theme->settings->marketing5target)) ? false :
            $this->page->theme->settings->marketing5target;
        $marketing5image = (empty($this->page->theme->settings->marketing5image)) ? false : 'marketing5image';

        $hasmarketing6 = (empty($this->page->theme->settings->marketing6
            && $this->page->theme->settings->togglemarketing == 1)) ? false :
            format_string($this->page->theme->settings->marketing6);
        $marketing6content = (empty($this->page->theme->settings->marketing6content)) ? false :
            format_text($this->page->theme->settings->marketing6content);
        $marketing6buttontext = (empty($this->page->theme->settings->marketing6buttontext)) ? false :
            format_string($this->page->theme->settings->marketing6buttontext);
        $marketing6buttonurl = (empty($this->page->theme->settings->marketing6buttonurl)) ? false :
            $this->page->theme->settings->marketing6buttonurl;
        $marketing6target = (empty($this->page->theme->settings->marketing6target)) ? false :
            $this->page->theme->settings->marketing6target;
        $marketing6image = (empty($this->page->theme->settings->marketing6image)) ? false : 'marketing6image';

        $hasmarketing7 = (empty($this->page->theme->settings->marketing7
            && $this->page->theme->settings->togglemarketing == 1)) ? false :
            format_string($this->page->theme->settings->marketing7);
        $marketing7content = (empty($this->page->theme->settings->marketing7content)) ? false :
            format_text($this->page->theme->settings->marketing7content);
        $marketing7buttontext = (empty($this->page->theme->settings->marketing7buttontext)) ? false :
            format_string($this->page->theme->settings->marketing7buttontext);
        $marketing7buttonurl = (empty($this->page->theme->settings->marketing7buttonurl)) ? false :
            $this->page->theme->settings->marketing7buttonurl;
        $marketing7target = (empty($this->page->theme->settings->marketing7target)) ? false :
            $this->page->theme->settings->marketing7target;
        $marketing7image = (empty($this->page->theme->settings->marketing7image)) ? false : 'marketing7image';

        $hasmarketing8 = (empty($this->page->theme->settings->marketing8
            && $this->page->theme->settings->togglemarketing == 1)) ? false :
            format_string($this->page->theme->settings->marketing8);
        $marketing8content = (empty($this->page->theme->settings->marketing8content)) ? false :
            format_text($this->page->theme->settings->marketing8content);
        $marketing8buttontext = (empty($this->page->theme->settings->marketing8buttontext)) ? false :
            format_string($this->page->theme->settings->marketing8buttontext);
        $marketing8buttonurl = (empty($this->page->theme->settings->marketing8buttonurl)) ? false :
            $this->page->theme->settings->marketing8buttonurl;
        $marketing8target = (empty($this->page->theme->settings->marketing8target)) ? false :
            $this->page->theme->settings->marketing8target;
        $marketing8image = (empty($this->page->theme->settings->marketing8image)) ? false : 'marketing8image';

        $hasmarketing9 = (empty($this->page->theme->settings->marketing9 &&
            $this->page->theme->settings->togglemarketing == 1)) ? false :
            format_string($this->page->theme->settings->marketing9);
        $marketing9content = (empty($this->page->theme->settings->marketing9content)) ? false :
            format_text($this->page->theme->settings->marketing9content);
        $marketing9buttontext = (empty($this->page->theme->settings->marketing9buttontext)) ? false :
            format_string($this->page->theme->settings->marketing9buttontext);
        $marketing9buttonurl = (empty($this->page->theme->settings->marketing9buttonurl)) ? false :
            $this->page->theme->settings->marketing9buttonurl;
        $marketing9target = (empty($this->page->theme->settings->marketing9target)) ? false :
            $this->page->theme->settings->marketing9target;
        $marketing9image = (empty($this->page->theme->settings->marketing9image)) ? false : 'marketing9image';
        /* What does is the purpose of this codepart?
        if (method_exists(new \core\session\manager, 'get_login_token')) {
            $logintoken = \core\session\manager::get_login_token();
        } else {
            $logintoken = false;
        }
        if( method_exists ( "\core\session\manager", "get_login_token" ) ){
            $logintoken = s(\core\session\manager::get_login_token());
            echo '<input type="hidden" name="logintoken" value="' . $logintoken . '" />';
        } else {
            $logintoken = false;
        }
        */

        $logintoken = manager::get_login_token();

        $fpwonderboxcontext = ['logintoken' => $logintoken, 'hasfptextbox' =>
            (
            !empty($this->page->theme->settings->fptextbox && isloggedin())),
            'fptextbox' => $fptextbox, 'hasslidetextbox' =>
                (!empty($this->page->theme->settings->slidetextbox && isloggedin())),
            'slidetextbox' => $slidetextbox, 'hasfptextboxlogout' => !isloggedin(), 'fptextboxlogout' => $fptextboxlogout,
            'hasshowloginform' => $this->page->theme->settings->showloginform, 'alertbox' => $alertbox,
            'hasmarkettiles' =>
                ($hasmarketing1 || $hasmarketing2 || $hasmarketing3 ||
                    $hasmarketing4 || $hasmarketing5 || $hasmarketing6) ? true : false,
            'markettiles' => [
                [
                    'hastile' => $hasmarketing1,
                    'tileimage' => $marketing1image,
                    'content' => $marketing1content,
                    'title' => $hasmarketing1,
                    'button' => "<a href = '$marketing1buttonurl' title = '$marketing1buttontext' " .
                        "alt='$marketing1buttontext' class='btn btn-primary' target='$marketing1target'> $marketing1buttontext </a>"
                ],
                [
                    'hastile' => $hasmarketing2,
                    'tileimage' => $marketing2image,
                    'content' => $marketing2content,
                    'title' => $hasmarketing2,
                    'button' => "<a href = '$marketing2buttonurl' title = '$marketing2buttontext' " .
                        "alt='$marketing2buttontext' class='btn btn-primary' target='$marketing2target'> $marketing2buttontext </a>"
                ],
                [
                    'hastile' => $hasmarketing3,
                    'tileimage' => $marketing3image,
                    'content' => $marketing3content,
                    'title' => $hasmarketing3,
                    'button' => "<a href = '$marketing3buttonurl' title = '$marketing3buttontext' " .
                        "alt='$marketing3buttontext' class='btn btn-primary' target='$marketing3target'> $marketing3buttontext </a>"
                ],
                [
                    'hastile' => $hasmarketing4,
                    'tileimage' => $marketing4image,
                    'content' => $marketing4content,
                    'title' => $hasmarketing4,
                    'button' => "<a href = '$marketing4buttonurl' title = '$marketing4buttontext' " .
                        "alt='$marketing4buttontext' class='btn btn-primary' target='$marketing4target'> $marketing4buttontext </a>"
                ],
                [
                    'hastile' => $hasmarketing5,
                    'tileimage' => $marketing5image,
                    'content' => $marketing5content,
                    'title' => $hasmarketing5,
                    'button' => "<a href = '$marketing5buttonurl' title = '$marketing5buttontext' " .
                        "alt='$marketing5buttontext' class='btn btn-primary' target='$marketing5target'> $marketing5buttontext </a>"
                ],
                [
                    'hastile' => $hasmarketing6,
                    'tileimage' => $marketing6image,
                    'content' => $marketing6content,
                    'title' => $hasmarketing6,
                    'button' => "<a href = '$marketing6buttonurl' title = '$marketing6buttontext' " .
                        "alt='$marketing6buttontext' class='btn btn-primary' target='$marketing6target'> $marketing6buttontext </a>"
                ],
                [
                    'hastile' => $hasmarketing7,
                    'tileimage' => $marketing7image,
                    'content' => $marketing7content,
                    'title' => $hasmarketing7,
                    'button' => "<a href = '$marketing7buttonurl' title = '$marketing7buttontext' " .
                        "alt='$marketing7buttontext' class='btn btn-primary' target='$marketing7target'> $marketing7buttontext </a>"
                ],
                [
                    'hastile' => $hasmarketing8,
                    'tileimage' => $marketing8image,
                    'content' => $marketing8content,
                    'title' => $hasmarketing8,
                    'button' => "<a href = '$marketing8buttonurl' title = '$marketing8buttontext' " .
                        "alt='$marketing8buttontext' class='btn btn-primary' target='$marketing8target'> $marketing8buttontext </a>"
                ],
                [
                    'hastile' => $hasmarketing9,
                    'tileimage' => $marketing9image,
                    'content' => $marketing9content,
                    'title' => $hasmarketing9,
                    'button' => "<a href = '$marketing9buttonurl' title = '$marketing9buttontext' " .
                        "alt='$marketing9buttontext' class='btn btn-primary' target='$marketing9target'> $marketing9buttontext </a>"
                ],
            ],
            // If any of the above social networks are true, sets this to true.
            'hasfpiconnav' => ($hasnav1icon || $hasnav2icon || $hasnav3icon || $hasnav4icon || $hasnav5icon ||
                $hasnav6icon || $hasnav7icon || $hasnav8icon || $hascreateicon || $hasslideicon) ? true : false, 'fpiconnav' => [
                [
                    'hasicon' => $hasnav1icon,
                    'linkicon' => $hasnav1icon,
                    'link' => $nav1buttonurl,
                    'linktext' => $nav1buttontext,
                    'linktarget' => $nav1target
                ],
                [
                    'hasicon' => $hasnav2icon,
                    'linkicon' => $hasnav2icon,
                    'link' => $nav2buttonurl,
                    'linktext' => $nav2buttontext,
                    'linktarget' => $nav2target
                ],
                [
                    'hasicon' => $hasnav3icon,
                    'linkicon' => $hasnav3icon,
                    'link' => $nav3buttonurl,
                    'linktext' => $nav3buttontext,
                    'linktarget' => $nav3target
                ],
                [
                    'hasicon' => $hasnav4icon,
                    'linkicon' => $hasnav4icon,
                    'link' => $nav4buttonurl,
                    'linktext' => $nav4buttontext,
                    'linktarget' => $nav4target
                ],
                [
                    'hasicon' => $hasnav5icon,
                    'linkicon' => $hasnav5icon,
                    'link' => $nav5buttonurl,
                    'linktext' => $nav5buttontext,
                    'linktarget' => $nav5target
                ],
                [
                    'hasicon' => $hasnav6icon,
                    'linkicon' => $hasnav6icon,
                    'link' => $nav6buttonurl,
                    'linktext' => $nav6buttontext,
                    'linktarget' => $nav6target
                ],
                [
                    'hasicon' => $hasnav7icon,
                    'linkicon' => $hasnav7icon,
                    'link' => $nav7buttonurl,
                    'linktext' => $nav7buttontext,
                    'linktarget' => $nav7target
                ],
                [
                    'hasicon' => $hasnav8icon,
                    'linkicon' => $hasnav8icon,
                    'link' => $nav8buttonurl,
                    'linktext' => $nav8buttontext,
                    'linktarget' => $nav8target
                ],
            ], 'fpcreateicon' => [
                [
                    'hasicon' => $hascreateicon,
                    'linkicon' => $hascreateicon,
                    'link' => $createbuttonurl,
                    'linktext' => $createbuttontext
                ],
            ], 'fpslideicon' => [
                [
                    'hasicon' => $hasslideicon,
                    'linkicon' => $hasslideicon,
                    'link' => $slideiconbuttonurl,
                    'linktext' => $slideiconbuttontext
                ],
            ]];
        return $this->render_from_template('theme_fordson/fpwonderbox', $fpwonderboxcontext);
    }

    public function customlogin() {
        $hasloginnav1icon = (empty($this->page->theme->settings->loginnav1icon)) ? false :
            $this->page->theme->settings->loginnav1icon;
        $hasloginnav2icon = (empty($this->page->theme->settings->loginnav2icon)) ? false :
            $this->page->theme->settings->loginnav2icon;
        $hasloginnav3icon = (empty($this->page->theme->settings->loginnav3icon)) ? false :
            $this->page->theme->settings->loginnav3icon;
        $hasloginnav4icon = (empty($this->page->theme->settings->loginnav4icon)) ? false :
            $this->page->theme->settings->loginnav4icon;
        $loginnav1titletext = (empty($this->page->theme->settings->loginnav1titletext)) ? false :
            format_text($this->page->theme->settings->loginnav1titletext);
        $loginnav2titletext = (empty($this->page->theme->settings->loginnav2titletext)) ? false :
            format_text($this->page->theme->settings->loginnav2titletext);
        $loginnav3titletext = (empty($this->page->theme->settings->loginnav3titletext)) ? false :
            format_text($this->page->theme->settings->loginnav3titletext);
        $loginnav4titletext = (empty($this->page->theme->settings->loginnav4titletext)) ? false :
            format_text($this->page->theme->settings->loginnav4titletext);
        $loginnav1icontext = (empty($this->page->theme->settings->loginnav1icontext)) ? false :
            format_text($this->page->theme->settings->loginnav1icontext);
        $loginnav2icontext = (empty($this->page->theme->settings->loginnav2icontext)) ? false :
            format_text($this->page->theme->settings->loginnav2icontext);
        $loginnav3icontext = (empty($this->page->theme->settings->loginnav3icontext)) ? false :
            format_text($this->page->theme->settings->loginnav3icontext);
        $loginnav4icontext = (empty($this->page->theme->settings->loginnav4icontext)) ? false :
            format_text($this->page->theme->settings->loginnav4icontext);
        $hascustomlogin = $this->page->theme->settings->showcustomlogin == 1;
        $hasdefaultlogin = $this->page->theme->settings->showcustomlogin == 0;
        $customlogincontextentry = [
            'hascustomlogin' => $hascustomlogin,
            'hasdefaultlogin' => $hasdefaultlogin,
            'hasfeature1' => !empty($this->page->theme->setting_file_url('feature1image',
                    'feature1image')) && !empty($this->page->theme->settings->feature1text), 'hasfeature2' =>
                !empty($this->page->theme->setting_file_url('feature2image',
                    'feature2image')) && !empty($this->page->theme->settings->feature2text), 'hasfeature3' =>
                !empty($this->page->theme->setting_file_url('feature3image',
                    'feature3image')) && !empty($this->page->theme->settings->feature3text),
            'feature1image' => $this->page->theme->setting_file_url('feature1image',
                'feature1image'), 'feature2image' => $this->page->theme->setting_file_url('feature2image',
                'feature2image'), 'feature3image' => $this->page->theme->setting_file_url('feature3image',
                'feature3image'), 'feature1text' => (empty($this->page->theme->settings->feature1text)) ? false :
                format_text($this->page->theme->settings->feature1text,
                    FORMAT_HTML,
                    [
                        'noclean' => true
                    ]), 'feature2text' => (empty($this->page->theme->settings->feature2text)) ? false :
                format_text($this->page->theme->settings->feature2text,
                    FORMAT_HTML,
                    [
                        'noclean' => true
                    ]), 'feature3text' => (empty($this->page->theme->settings->feature3text)) ? false :
                format_text($this->page->theme->settings->feature3text,
                    FORMAT_HTML,
                    [
                        'noclean' => true
                    ]),
            // If any of the above social networks are true, sets this to true.
            'hasfpiconnav' => ($hasloginnav1icon || $hasloginnav2icon || $hasloginnav3icon || $hasloginnav4icon) ? true : false,
            'fpiconnav' => [
                [
                    'hasicon' => $hasloginnav1icon,
                    'icon' => $hasloginnav1icon,
                    'title' => $loginnav1titletext,
                    'text' => $loginnav1icontext
                ],
                [
                    'hasicon' => $hasloginnav2icon,
                    'icon' => $hasloginnav2icon,
                    'title' => $loginnav2titletext,
                    'text' => $loginnav2icontext
                ],
                [
                    'hasicon' => $hasloginnav3icon,
                    'icon' => $hasloginnav3icon,
                    'title' => $loginnav3titletext,
                    'text' => $loginnav3icontext
                ],
                [
                    'hasicon' => $hasloginnav4icon,
                    'icon' => $hasloginnav4icon,
                    'title' => $loginnav4titletext,
                    'text' => $loginnav4icontext
                ],
            ]];
        return $this->render_from_template('theme_fordson/customlogin', $customlogincontextentry);
    }

    public function fp_marketingtiles() {
        $hasmarketing1 = (empty($this->page->theme->settings->marketing1
            && $this->page->theme->settings->togglemarketing == 2)) ? false :
            format_string($this->page->theme->settings->marketing1);
        $marketing1content = (empty($this->page->theme->settings->marketing1content)) ? false :
            format_text($this->page->theme->settings->marketing1content);
        $marketing1buttontext = (empty($this->page->theme->settings->marketing1buttontext)) ? false :
            format_string($this->page->theme->settings->marketing1buttontext);
        $marketing1buttonurl = (empty($this->page->theme->settings->marketing1buttonurl)) ? false :
            $this->page->theme->settings->marketing1buttonurl;
        $marketing1target = (empty($this->page->theme->settings->marketing1target)) ? false :
            $this->page->theme->settings->marketing1target;
        $marketing1image = (empty($this->page->theme->settings->marketing1image)) ? false : 'marketing1image';

        $hasmarketing2 = (empty($this->page->theme->settings->marketing2
            && $this->page->theme->settings->togglemarketing == 2)) ? false :
            format_string($this->page->theme->settings->marketing2);
        $marketing2content = (empty($this->page->theme->settings->marketing2content)) ? false :
            format_text($this->page->theme->settings->marketing2content);
        $marketing2buttontext = (empty($this->page->theme->settings->marketing2buttontext)) ? false :
            format_string($this->page->theme->settings->marketing2buttontext);
        $marketing2buttonurl = (empty($this->page->theme->settings->marketing2buttonurl)) ? false :
            $this->page->theme->settings->marketing2buttonurl;
        $marketing2target = (empty($this->page->theme->settings->marketing2target)) ? false :
            $this->page->theme->settings->marketing2target;
        $marketing2image = (empty($this->page->theme->settings->marketing2image)) ? false : 'marketing2image';

        $hasmarketing3 = (empty($this->page->theme->settings->marketing3
            && $this->page->theme->settings->togglemarketing == 2)) ? false :
            format_string($this->page->theme->settings->marketing3);
        $marketing3content = (empty($this->page->theme->settings->marketing3content)) ? false :
            format_text($this->page->theme->settings->marketing3content);
        $marketing3buttontext = (empty($this->page->theme->settings->marketing3buttontext)) ? false :
            format_string($this->page->theme->settings->marketing3buttontext);
        $marketing3buttonurl = (empty($this->page->theme->settings->marketing3buttonurl)) ? false :
            $this->page->theme->settings->marketing3buttonurl;
        $marketing3target = (empty($this->page->theme->settings->marketing3target)) ? false :
            $this->page->theme->settings->marketing3target;
        $marketing3image = (empty($this->page->theme->settings->marketing3image)) ? false : 'marketing3image';

        $hasmarketing4 = (empty($this->page->theme->settings->marketing4
            && $this->page->theme->settings->togglemarketing == 2)) ? false :
            format_string($this->page->theme->settings->marketing4);
        $marketing4content = (empty($this->page->theme->settings->marketing4content)) ? false :
            format_text($this->page->theme->settings->marketing4content);
        $marketing4buttontext = (empty($this->page->theme->settings->marketing4buttontext)) ? false :
            format_string($this->page->theme->settings->marketing4buttontext);
        $marketing4buttonurl = (empty($this->page->theme->settings->marketing4buttonurl)) ? false :
            $this->page->theme->settings->marketing4buttonurl;
        $marketing4target = (empty($this->page->theme->settings->marketing4target)) ? false :
            $this->page->theme->settings->marketing4target;
        $marketing4image = (empty($this->page->theme->settings->marketing4image)) ? false : 'marketing4image';

        $hasmarketing5 = (empty($this->page->theme->settings->marketing5
            && $this->page->theme->settings->togglemarketing == 2)) ? false :
            format_string($this->page->theme->settings->marketing5);
        $marketing5content = (empty($this->page->theme->settings->marketing5content)) ? false :
            format_text($this->page->theme->settings->marketing5content);
        $marketing5buttontext = (empty($this->page->theme->settings->marketing5buttontext)) ? false :
            format_string($this->page->theme->settings->marketing5buttontext);
        $marketing5buttonurl = (empty($this->page->theme->settings->marketing5buttonurl)) ? false :
            $this->page->theme->settings->marketing5buttonurl;
        $marketing5target = (empty($this->page->theme->settings->marketing5target)) ? false :
            $this->page->theme->settings->marketing5target;
        $marketing5image = (empty($this->page->theme->settings->marketing5image)) ? false : 'marketing5image';

        $hasmarketing6 = (empty($this->page->theme->settings->marketing6
            && $this->page->theme->settings->togglemarketing == 2)) ? false :
            format_string($this->page->theme->settings->marketing6);
        $marketing6content = (empty($this->page->theme->settings->marketing6content)) ? false :
            format_text($this->page->theme->settings->marketing6content);
        $marketing6buttontext = (empty($this->page->theme->settings->marketing6buttontext)) ? false :
            format_string($this->page->theme->settings->marketing6buttontext);
        $marketing6buttonurl = (empty($this->page->theme->settings->marketing6buttonurl)) ? false :
            $this->page->theme->settings->marketing6buttonurl;
        $marketing6target = (empty($this->page->theme->settings->marketing6target)) ? false :
            $this->page->theme->settings->marketing6target;
        $marketing6image = (empty($this->page->theme->settings->marketing6image)) ? false : 'marketing6image';

        $hasmarketing7 = (empty($this->page->theme->settings->marketing7
            && $this->page->theme->settings->togglemarketing == 2)) ? false :
            format_string($this->page->theme->settings->marketing7);
        $marketing7content = (empty($this->page->theme->settings->marketing7content)) ? false :
            format_text($this->page->theme->settings->marketing7content);
        $marketing7buttontext = (empty($this->page->theme->settings->marketing7buttontext)) ? false :
            format_string($this->page->theme->settings->marketing7buttontext);
        $marketing7buttonurl = (empty($this->page->theme->settings->marketing7buttonurl)) ? false :
            $this->page->theme->settings->marketing7buttonurl;
        $marketing7target = (empty($this->page->theme->settings->marketing7target)) ? false :
            $this->page->theme->settings->marketing7target;
        $marketing7image = (empty($this->page->theme->settings->marketing7image)) ? false : 'marketing7image';

        $hasmarketing8 = (empty($this->page->theme->settings->marketing8
            && $this->page->theme->settings->togglemarketing == 2)) ? false :
            format_string($this->page->theme->settings->marketing8);
        $marketing8content = (empty($this->page->theme->settings->marketing8content)) ? false :
            format_text($this->page->theme->settings->marketing8content);
        $marketing8buttontext = (empty($this->page->theme->settings->marketing8buttontext)) ? false :
            format_string($this->page->theme->settings->marketing8buttontext);
        $marketing8buttonurl = (empty($this->page->theme->settings->marketing8buttonurl)) ? false :
            $this->page->theme->settings->marketing8buttonurl;
        $marketing8target = (empty($this->page->theme->settings->marketing8target)) ? false :
            $this->page->theme->settings->marketing8target;
        $marketing8image = (empty($this->page->theme->settings->marketing8image)) ? false : 'marketing8image';

        $hasmarketing9 = (empty($this->page->theme->settings->marketing9
            && $this->page->theme->settings->togglemarketing == 2)) ? false :
            format_string($this->page->theme->settings->marketing9);
        $marketing9content = (empty($this->page->theme->settings->marketing9content)) ? false :
            format_text($this->page->theme->settings->marketing9content);
        $marketing9buttontext = (empty($this->page->theme->settings->marketing9buttontext)) ? false :
            format_string($this->page->theme->settings->marketing9buttontext);
        $marketing9buttonurl = (empty($this->page->theme->settings->marketing9buttonurl)) ? false :
            $this->page->theme->settings->marketing9buttonurl;
        $marketing9target = (empty($this->page->theme->settings->marketing9target)) ? false :
            $this->page->theme->settings->marketing9target;
        $marketing9image = (empty($this->page->theme->settings->marketing9image)) ? false : 'marketing9image';

        $fpmarketingtilesentry = ['hasmarkettiles' =>
            ($hasmarketing1 || $hasmarketing2 || $hasmarketing3 || $hasmarketing4 || $hasmarketing5 || $hasmarketing6)
                ? true : false,
            'markettiles' => [
                [
                    'hastile' => $hasmarketing1,
                    'tileimage' => $marketing1image,
                    'content' => $marketing1content,
                    'title' => $hasmarketing1,
                    'button' => "<a href = '$marketing1buttonurl' title = '$marketing1buttontext' " .
                        "alt='$marketing1buttontext' class='btn btn-primary' target='$marketing1target'> $marketing1buttontext </a>"
                ],
                [
                    'hastile' => $hasmarketing2,
                    'tileimage' => $marketing2image,
                    'content' => $marketing2content,
                    'title' => $hasmarketing2,
                    'button' => "<a href = '$marketing2buttonurl' title = '$marketing2buttontext' " .
                        "alt='$marketing2buttontext' class='btn btn-primary' target='$marketing2target'> $marketing2buttontext </a>"
                ],
                [
                    'hastile' => $hasmarketing3,
                    'tileimage' => $marketing3image,
                    'content' => $marketing3content,
                    'title' => $hasmarketing3,
                    'button' => "<a href = '$marketing3buttonurl' title = '$marketing3buttontext' " .
                        "alt='$marketing3buttontext' class='btn btn-primary' target='$marketing3target'> $marketing3buttontext </a>"
                ],
                [
                    'hastile' => $hasmarketing4,
                    'tileimage' => $marketing4image,
                    'content' => $marketing4content,
                    'title' => $hasmarketing4,
                    'button' => "<a href = '$marketing4buttonurl' title = '$marketing4buttontext' " .
                        "alt='$marketing4buttontext' class='btn btn-primary' target='$marketing4target'> $marketing4buttontext </a>"
                ],
                [
                    'hastile' => $hasmarketing5,
                    'tileimage' => $marketing5image,
                    'content' => $marketing5content,
                    'title' => $hasmarketing5,
                    'button' => "<a href = '$marketing5buttonurl' title = '$marketing5buttontext' " .
                        "alt='$marketing5buttontext' class='btn btn-primary' target='$marketing5target'> $marketing5buttontext </a>"
                ],
                [
                    'hastile' => $hasmarketing6,
                    'tileimage' => $marketing6image,
                    'content' => $marketing6content,
                    'title' => $hasmarketing6,
                    'button' => "<a href = '$marketing6buttonurl' title = '$marketing6buttontext' " .
                        "alt='$marketing6buttontext' class='btn btn-primary' target='$marketing6target'> $marketing6buttontext </a>"
                ],
                [
                    'hastile' => $hasmarketing7,
                    'tileimage' => $marketing7image,
                    'content' => $marketing7content,
                    'title' => $hasmarketing7,
                    'button' => "<a href = '$marketing7buttonurl' title = '$marketing7buttontext' " .
                        "alt='$marketing7buttontext' class='btn btn-primary' target='$marketing7target'> $marketing7buttontext </a>"
                ],
                [
                    'hastile' => $hasmarketing8,
                    'tileimage' => $marketing8image,
                    'content' => $marketing8content,
                    'title' => $hasmarketing8,
                    'button' => "<a href = '$marketing8buttonurl' title = '$marketing8buttontext' " .
                        "alt='$marketing8buttontext' class='btn btn-primary' target='$marketing8target'> $marketing8buttontext </a>"
                ],
                [
                    'hastile' => $hasmarketing9,
                    'tileimage' => $marketing9image,
                    'content' => $marketing9content,
                    'title' => $hasmarketing9,
                    'button' => "<a href = '$marketing9buttonurl' title = '$marketing9buttontext' " .
                        "alt='$marketing9buttontext' class='btn btn-primary' target='$marketing9target'> $marketing9buttontext </a>"
                ],
            ]];
        return $this->render_from_template('theme_fordson/fpmarkettiles', $fpmarketingtilesentry);
    }

    public function fp_slideshow() {
        $theme = theme_config::load('fordson');
        $slideshowon = $this->page->theme->settings->showslideshow == 1;
        $hasslide1 = (empty($theme->setting_file_url('slide1image',
            'slide1image'))) ? false : $theme->setting_file_url('slide1image', 'slide1image');
        $slide1 = (empty($this->page->theme->settings->slide1title)) ? false : $this->page->theme->settings->slide1title;
        $slide1content = (empty($this->page->theme->settings->slide1content)) ? false :
            format_text($this->page->theme->settings->slide1content);
        $showtext1 = (empty($this->page->theme->settings->slide1title)) ? false :
            format_text($this->page->theme->settings->slide1title);
        $hasslide2 = (empty($theme->setting_file_url('slide2image',
            'slide2image'))) ? false : $theme->setting_file_url('slide2image', 'slide2image');
        $slide2 = (empty($this->page->theme->settings->slide2title)) ? false : $this->page->theme->settings->slide2title;
        $slide2content = (empty($this->page->theme->settings->slide2content)) ? false :
            format_text($this->page->theme->settings->slide2content);
        $showtext2 = (empty($this->page->theme->settings->slide2title)) ? false :
            format_text($this->page->theme->settings->slide2title);
        $hasslide3 = (empty($theme->setting_file_url('slide3image',
            'slide3image'))) ? false : $theme->setting_file_url('slide3image', 'slide3image');
        $slide3 = (empty($this->page->theme->settings->slide3title)) ? false :
            $this->page->theme->settings->slide3title;
        $slide3content = (empty($this->page->theme->settings->slide3content)) ? false :
            format_text($this->page->theme->settings->slide3content);
        $showtext3 = (empty($this->page->theme->settings->slide3title)) ? false :
            format_text($this->page->theme->settings->slide3title);
        $fpslideshowentry = [
            'hasfpslideshow' => $slideshowon,
            'hasslide1' => $hasslide1 ? true : false,
            'hasslide2' => $hasslide2 ? true : false,
            'hasslide3' => $hasslide3 ? true : false,
            'showtext1' => $showtext1 ? true : false,
            'showtext2' => $showtext2 ? true : false,
            'showtext3' => $showtext3 ? true : false,
            'slide1' => [
                'slidetitle' => $slide1,
                'slidecontent' => $slide1content
            ], 'slide2' => [
                'slidetitle' => $slide2,
                'slidecontent' => $slide2content
            ], 'slide3' => [
                'slidetitle' => $slide3,
                'slidecontent' => $slide3content
            ]];
        return $this->render_from_template('theme_fordson/slideshow', $fpslideshowentry);
    }

    public function teacherdashmenu() {
        global $COURSE, $CFG, $DB;
        $course = $this->page->course;
        $context = context_course::instance($course->id);
        $showincourseonly = isset($COURSE->id) && $COURSE->id > 1 && $this->page->theme->settings->coursemanagementtoggle
            && isloggedin() && !isguestuser();
        $haspermission = has_capability('enrol/category:config',
                $context) && $this->page->theme->settings->coursemanagementtoggle && isset($COURSE->id) && $COURSE->id > 1;
        $togglebutton = '';
        $togglebuttonstudent = '';
        $hasteacherdash = '';
        $hasstudentdash = '';
        $globalhaseasyenrollment = enrol_get_plugin('easy');
        $coursehaseasyenrollment = '';
        if ($globalhaseasyenrollment) {
            $coursehaseasyenrollment = $DB->record_exists('enrol',
                [
                    'courseid' => $COURSE->id,
                    'enrol' => 'easy'
                ]);
            $easyenrollinstance = $DB->get_record('enrol',
                [
                    'courseid' => $COURSE->id,
                    'enrol' => 'easy'
                ]);
        }
        if ($coursehaseasyenrollment && isset($COURSE->id) && $COURSE->id > 1) {
            $easycodetitle = get_string('header_coursecodes', 'enrol_easy');
            $easycodelink = new moodle_url('/enrol/editinstance.php', [
                'courseid' => $this->page->course->id,
                'id' => $easyenrollinstance->id,
                'type' => 'easy'
            ]);
        }
        if (isloggedin() && isset($COURSE->id) && $COURSE->id > 1) {
            $course = $this->page->course;
            $context = context_course::instance($course->id);
            $hasteacherdash = has_capability('moodle/course:viewhiddenactivities', $context);
            $hasstudentdash = !has_capability('moodle/course:viewhiddenactivities', $context);
            if (has_capability('moodle/course:viewhiddenactivities', $context)) {
                $togglebutton = get_string('coursemanagementbutton', 'theme_fordson');
            } else {
                $togglebuttonstudent = get_string('studentdashbutton', 'theme_fordson');
            }
        }
        $siteadmintitle = get_string('siteadminquicklink', 'theme_fordson');
        $siteadminurl = new moodle_url('/admin/search.php');
        $hasadminlink = has_capability('moodle/site:configview', $context);
        $course = $this->page->course;
        // Send to template.
        $dashmenu = ['showincourseonly' => $showincourseonly, 'togglebutton' => $togglebutton,
            'togglebuttonstudent' => $togglebuttonstudent, 'hasteacherdash' => $hasteacherdash,
            'hasstudentdash' => $hasstudentdash, 'haspermission' => $haspermission, 'hasadminlink' => $hasadminlink,
            'siteadmintitle' => $siteadmintitle, 'siteadminurl' => $siteadminurl];
        // Attach easy enrollment links if active.
        if ($globalhaseasyenrollment && $coursehaseasyenrollment) {
            $dashmenu['dashmenu'][] = [
                'haseasyenrollment' => $coursehaseasyenrollment,
                'title' => $easycodetitle,
                'url' => $easycodelink
            ];
        }
        return $this->render_from_template('theme_fordson/teacherdashmenu', $dashmenu);
    }

    public function teacherdash() {
        global $COURSE, $CFG, $DB, $USER;
        require_once($CFG->dirroot . '/completion/classes/progress.php');
        $togglebutton = '';
        $togglebuttonstudent = '';
        $hasteacherdash = '';
        $hasstudentdash = '';
        $haseditcog = $this->page->theme->settings->courseeditingcog;
        $editcog = html_writer::div($this->context_header_settings_menu(),
            'pull-xs-right context-header-settings-menu');
        if (isloggedin() && isset($COURSE->id) && $COURSE->id > 1) {
            $course = $this->page->course;
            $context = context_course::instance($course->id);
            $hasteacherdash = has_capability('moodle/course:viewhiddenactivities', $context);
            $hasstudentdash = !has_capability('moodle/course:viewhiddenactivities', $context);
            if (has_capability('moodle/course:viewhiddenactivities', $context)) {
                $togglebutton = get_string('coursemanagementbutton', 'theme_fordson');
            } else {
                $togglebuttonstudent = get_string('studentdashbutton', 'theme_fordson');
            }
        }
        $course = $this->page->course;
        $context = context_course::instance($course->id);
        $coursemanagementmessage = (empty($this->page->theme->settings->coursemanagementtextbox)) ? false :
            format_text($this->page->theme->settings->coursemanagementtextbox);
        $courseactivities = $this->courseactivities_menu();
        $showincourseonly = isset($COURSE->id) && $COURSE->id > 1
            && $this->page->theme->settings->coursemanagementtoggle && isloggedin() && !isguestuser();
        $globalhaseasyenrollment = enrol_get_plugin('easy');
        $coursehaseasyenrollment = '';
        if ($globalhaseasyenrollment) {
            $coursehaseasyenrollment = $DB->record_exists('enrol',
                [
                    'courseid' => $COURSE->id,
                    'enrol' => 'easy'
                ]);
            $easyenrollinstance = $DB->get_record('enrol',
                [
                    'courseid' => $COURSE->id,
                    'enrol' => 'easy'
                ]);
        }
        // Link catagories.
        $haspermission = has_capability('enrol/category:config',
                $context) && $this->page->theme->settings->coursemanagementtoggle && isset($COURSE->id) && $COURSE->id > 1;
        $userlinks = get_string('userlinks', 'theme_fordson');
        $userlinksdesc = get_string('userlinks_desc', 'theme_fordson');
        $qbank = get_string('qbank', 'theme_fordson');
        $qbankdesc = get_string('qbank_desc', 'theme_fordson');
        $badges = get_string('badges', 'theme_fordson');
        $badgesdesc = get_string('badges_desc', 'theme_fordson');
        $coursemanage = get_string('coursemanage', 'theme_fordson');
        $coursemanagedesc = get_string('coursemanage_desc', 'theme_fordson');
        $coursemanagementmessage = (empty($this->page->theme->settings->coursemanagementtextbox)) ? false :
            format_text($this->page->theme->settings->coursemanagementtextbox,
                FORMAT_HTML,
                [
                    'noclean' => true
                ]);
        $studentdashboardtextbox = (empty($this->page->theme->settings->studentdashboardtextbox)) ? false :
            format_text($this->page->theme->settings->studentdashboardtextbox,
                FORMAT_HTML,
                [
                    'noclean' => true
                ]);
        // User links.
        if ($coursehaseasyenrollment && isset($COURSE->id) && $COURSE->id > 1) {
            $easycodetitle = get_string('header_coursecodes', 'enrol_easy');
            $easycodelink = new moodle_url('/enrol/editinstance.php', [
                'courseid' => $this->page->course->id,
                'id' => $easyenrollinstance->id,
                'type' => 'easy'
            ]);
        }
        $gradestitle = get_string('gradebooksetup', 'grades');
        $gradeslink = new moodle_url('/grade/edit/tree/index.php', [
            'id' => $this->page->course->id
        ]);
        $gradebooktitle = get_string('gradebook', 'grades');
        $gradebooklink = new moodle_url('/grade/report/grader/index.php', [
            'id' => $this->page->course->id
        ]);
        $participantstitle = ($this->page->theme->settings->studentdashboardtextbox == 1) ? false :
            get_string('participants',
                'moodle');
        $participantslink = new moodle_url('/user/index.php', [
            'id' => $this->page->course->id
        ]);
        (empty($participantstitle)) ? false : get_string('participants', 'moodle');
        $activitycompletiontitle = get_string('activitycompletion', 'completion');
        $activitycompletionlink = new moodle_url('/report/progress/index.php', [
            'course' => $this->page->course->id
        ]);
        $grouptitle = get_string('groups', 'group');
        $grouplink = new moodle_url('/group/index.php', [
            'id' => $this->page->course->id
        ]);
        $enrolmethodtitle = get_string('enrolmentinstances', 'enrol');
        $enrolmethodlink = new moodle_url('/enrol/instances.php', [
            'id' => $this->page->course->id
        ]);
        // User reports.
        $logstitle = get_string('logs', 'moodle');
        $logslink = new moodle_url('/report/log/index.php', [
            'id' => $this->page->course->id
        ]);
        $livelogstitle = get_string('loglive:view', 'report_loglive');
        $livelogslink = new moodle_url('/report/loglive/index.php', [
            'id' => $this->page->course->id
        ]);
        $participationtitle = get_string('participation:view', 'report_participation');
        $participationlink = new moodle_url('/report/participation/index.php', [
            'id' => $this->page->course->id
        ]);
        $activitytitle = get_string('outline:view', 'report_outline');
        $activitylink = new moodle_url('/report/outline/index.php', [
            'id' => $this->page->course->id
        ]);
        $completionreporttitle = get_string('coursecompletion', 'completion');
        $completionreportlink = new moodle_url('/report/completion/index.php', [
            'course' => $this->page->course->id
        ]);
        // Questionbank.
        $qbanktitle = get_string('questionbank', 'question');
        $qbanklink = new moodle_url('/question/edit.php', [
            'courseid' => $this->page->course->id
        ]);
        $qcattitle = get_string('questioncategory', 'question');
        $qcatlink = new moodle_url('/question/category.php', [
            'courseid' => $this->page->course->id
        ]);
        $qimporttitle = get_string('import', 'question');
        $qimportlink = new moodle_url('/question/import.php', [
            'courseid' => $this->page->course->id
        ]);
        $qexporttitle = get_string('export', 'question');
        $qexportlink = new moodle_url('/question/export.php', [
            'courseid' => $this->page->course->id
        ]);
        // Manage course.
        $courseadmintitle = get_string('courseadministration', 'moodle');
        $courseadminlink = new moodle_url('/course/admin.php', [
            'courseid' => $this->page->course->id
        ]);
        $coursecompletiontitle = get_string('editcoursecompletionsettings', 'completion');
        $coursecompletionlink = new moodle_url('/course/completion.php', [
            'id' => $this->page->course->id
        ]);
        $competencytitle = get_string('competencies', 'competency');
        $competencyurl = new moodle_url('/admin/tool/lp/coursecompetencies.php', [
            'courseid' => $this->page->course->id
        ]);
        $courseresettitle = get_string('reset', 'moodle');
        $courseresetlink = new moodle_url('/course/reset.php', [
            'id' => $this->page->course->id
        ]);
        $coursebackuptitle = get_string('backup', 'moodle');
        $coursebackuplink = new moodle_url('/backup/backup.php', [
            'id' => $this->page->course->id
        ]);
        $courserestoretitle = get_string('restore', 'moodle');
        $courserestorelink = new moodle_url('/backup/restorefile.php', [
            'contextid' => $this->page->context->id
        ]);
        $courseimporttitle = get_string('import', 'moodle');
        $courseimportlink = new moodle_url('/backup/import.php', [
            'id' => $this->page->course->id
        ]);
        $courseedittitle = get_string('editcoursesettings', 'moodle');
        $courseeditlink = new moodle_url('/course/edit.php', [
            'id' => $this->page->course->id
        ]);
        $badgemanagetitle = get_string('managebadges', 'badges');
        $badgemanagelink = new moodle_url('/badges/index.php?type=2', [
            'id' => $this->page->course->id
        ]);
        $badgeaddtitle = get_string('newbadge', 'badges');
        $badgeaddlink = new moodle_url('/badges/newbadge.php?type=2', [
            'id' => $this->page->course->id
        ]);
        $recyclebintitle = get_string('pluginname', 'tool_recyclebin');
        $recyclebinlink = new moodle_url('/admin/tool/recyclebin/index.php', [
            'contextid' => $this->page->context->id
        ]);
        $filtertitle = get_string('filtersettings', 'filters');
        $filterlink = new moodle_url('/filter/manage.php', [
            'contextid' => $this->page->context->id
        ]);
        $eventmonitoringtitle = get_string('managesubscriptions', 'tool_monitor');
        $eventmonitoringlink = new moodle_url('/admin/tool/monitor/managerules.php', [
            'courseid' => $this->page->course->id
        ]);
        $copycoursetitle = get_string('copycourse', 'moodle');
        $copycourselink = new moodle_url('/backup/copy.php', [
            'id' => $this->page->course->id
        ]);

        // Student Dash.
        if (progress::get_course_progress_percentage($this->page->course)) {
            $comppc = progress::get_course_progress_percentage($this->page->course);
            $comppercent = number_format($comppc, 0);
        } else {
            $comppercent = 0;
        }

        $progresschartcontext = ['progress' => $comppercent];
        $progress = $this->render_from_template('theme_fordson/progress-bar', $progresschartcontext);

        $gradeslinkstudent = new moodle_url('/grade/report/user/index.php', [
            'id' => $this->page->course->id
        ]);
        $hascourseinfogroup = [
            'title' => get_string('courseinfo', 'theme_fordson'),
            'icon' => 'map'
        ];
        $summary = theme_fordson_strip_html_tags($COURSE->summary);
        $summarytrim = theme_fordson_course_trim_char($summary, 300);
        $courseinfo = [
            [
                'content' => format_text($summarytrim),
            ]
        ];
        $hascoursestaff = [
            'title' => get_string('coursestaff', 'theme_fordson'),
            'icon' => 'users'
        ];
        $courseteachers = [];
        $courseother = [];

        $showonlygroupteachers = !empty(groups_get_all_groups($course->id,
                $USER->id)) && $this->page->theme->settings->showonlygroupteachers == 1;
        if ($showonlygroupteachers) {
            $groupids = [];
            $studentgroups = groups_get_all_groups($course->id, $USER->id);
            foreach ($studentgroups as $grp) {
                $groupids[] = $grp->id;
            }
        }

        // If you created custom roles, please change the shortname value to match the name of your role.  This is teacher.
        $role = $DB->get_record('role',
            [
                'shortname' => 'editingteacher'
            ]);
        if ($role) {
            $context = context_course::instance($this->page->course->id);
            $teachers = get_role_users($role->id,
                $context,
                false,
                'u.id, u.firstname, u.middlename, u.lastname, u.alternatename,
                    u.firstnamephonetic, u.lastnamephonetic, u.email, u.picture, u.maildisplay,
                    u.imagealt');
            foreach ($teachers as $staff) {
                if ($showonlygroupteachers) {
                    $staffgroups = groups_get_all_groups($course->id, $staff->id);
                    $found = false;
                    foreach ($staffgroups as $grp) {
                        if (in_array($grp->id, $groupids)) {
                            $found = true;
                            break;
                        }
                    }
                    if (!$found) {
                        continue;
                    }
                }
                $picture = $this->user_picture($staff,
                    [
                        'size' => 50
                    ]);
                $messaging = new moodle_url('/message/index.php', [
                    'id' => $staff->id
                ]);
                $hasmessaging = $CFG->messaging == 1;
                $courseteachers[] = [
                    'name' => $staff->firstname . ' ' . $staff->lastname . ' ' . $staff->alternatename,
                    'email' => $staff->email,
                    'picture' => $picture,
                    'messaging' => $messaging,
                    'hasmessaging' => $hasmessaging,
                    'hasemail' => $staff->maildisplay
                ];
            }
        }
        // If you created custom roles, please change the shortname value to match the name of your role.
        // This is non-editing teacher.
        $role = $DB->get_record('role',
            [
                'shortname' => 'teacher'
            ]);
        if ($role) {
            $context = context_course::instance($this->page->course->id);
            $teachers = get_role_users($role->id,
                $context,
                false,
                'u.id, u.firstname, u.middlename, u.lastname, u.alternatename,
                    u.firstnamephonetic, u.lastnamephonetic, u.email, u.picture, u.maildisplay,
                    u.imagealt');
            foreach ($teachers as $staff) {
                if ($showonlygroupteachers) {
                    $staffgroups = groups_get_all_groups($course->id, $staff->id);
                    $found = false;
                    foreach ($staffgroups as $grp) {
                        if (in_array($grp->id, $groupids)) {
                            $found = true;
                            break;
                        }
                    }
                    if (!$found) {
                        continue;
                    }
                }
                $picture = $this->user_picture($staff,
                    [
                        'size' => 50
                    ]);
                $messaging = new moodle_url('/message/index.php', [
                    'id' => $staff->id
                ]);
                $hasmessaging = $CFG->messaging == 1;
                $courseother[] = [
                    'name' => $staff->firstname . ' ' . $staff->lastname,
                    'email' => $staff->email,
                    'picture' => $picture,
                    'messaging' => $messaging,
                    'hasmessaging' => $hasmessaging,
                    'hasemail' => $staff->maildisplay
                ];
            }
        }
        $activitylinkstitle = get_string('activitylinkstitle', 'theme_fordson');
        $activitylinkstitledesc = get_string('activitylinkstitle_desc', 'theme_fordson');
        $mygradestext = get_string('mygradestext', 'theme_fordson');
        $studentcoursemanage = get_string('courseadministration', 'moodle');
        // Permissionchecks for teacher access.
        $hasquestionpermission = has_capability('moodle/question:add', $context);
        $hasbadgepermission = has_capability('moodle/badges:awardbadge', $context);
        $hascoursepermission = has_capability('moodle/backup:backupcourse', $context);
        $hasuserpermission = has_capability('moodle/course:viewhiddenactivities', $context);
        $hasgradebookshow = $this->page->course->showgrades == 1 &&
            $this->page->theme->settings->showstudentgrades == 1;
        $hascompletionshow = $this->page->course->enablecompletion == 1 &&
            $this->page->theme->settings->showstudentcompletion == 1;
        $hascourseadminshow = $this->page->theme->settings->showcourseadminstudents == 1;
        $hascompetency = get_config('core_competency', 'enabled');
        // Send to template.
        $haseditcog = $this->page->theme->settings->courseeditingcog;
        $editcog = html_writer::div($this->context_header_settings_menu(),
            'pull-xs-right context-header-settings-menu');
        $dashlinks = [
            'showincourseonly' => $showincourseonly,
            'haspermission' => $haspermission,
            'courseactivities' => $courseactivities,
            'togglebutton' => $togglebutton,
            'togglebuttonstudent' => $togglebuttonstudent,
            'userlinkstitle' => $userlinks,
            'userlinksdesc' => $userlinksdesc,
            'qbanktitle' => $qbank,
            'activitylinkstitle' => $activitylinkstitle,
            'activitylinkstitle_desc' => $activitylinkstitledesc,
            'qbankdesc' => $qbankdesc,
            'badgestitle' => $badges,
            'badgesdesc' => $badgesdesc,
            'coursemanagetitle' => $coursemanage,
            'coursemanagedesc' => $coursemanagedesc,
            'coursemanagementmessage' => $coursemanagementmessage,
            'progress' => $progress,
            'gradeslink' => $gradeslink,
            'gradeslinkstudent' => $gradeslinkstudent,
            'hascourseinfogroup' => $hascourseinfogroup,
            'courseinfo' => $courseinfo,
            'hascoursestaffgroup' => $hascoursestaff,
            'courseteachers' => $courseteachers,
            'courseother' => $courseother,
            'mygradestext' => $mygradestext,
            'studentdashboardtextbox' => $studentdashboardtextbox,
            'hasteacherdash' => $hasteacherdash,
            'haseditcog' => $haseditcog,
            'editcog' => $editcog,
            'teacherdash' => [
                'hasquestionpermission' => $hasquestionpermission,
                'hasbadgepermission' => $hasbadgepermission,
                'hascoursepermission' => $hascoursepermission,
                'hasuserpermission' => $hasuserpermission
            ],
            'hasstudentdash' => $hasstudentdash,
            'hasgradebookshow' => $hasgradebookshow,
            'hascompletionshow' => $hascompletionshow,
            'studentcourseadminlink' => $courseadminlink,
            'studentcoursemanage' => $studentcoursemanage,
            'hascourseadminshow' => $hascourseadminshow,
            'hascompetency' => $hascompetency,
            'competencytitle' => $competencytitle,
            'competencyurl' => $competencyurl,
            'dashlinks' => [
                [
                    'hasuserlinks' => $gradebooktitle,
                    'title' => $gradebooktitle,
                    'url' => $gradebooklink
                ],
                [
                    'hasuserlinks' => $participantstitle,
                    'title' => $participantstitle,
                    'url' => $participantslink
                ],
                [
                    'hasuserlinks' => $grouptitle,
                    'title' => $grouptitle,
                    'url' => $grouplink
                ],
                [
                    'hasuserlinks' => $enrolmethodtitle,
                    'title' => $enrolmethodtitle,
                    'url' => $enrolmethodlink
                ],
                [
                    'hasuserlinks' => $activitycompletiontitle,
                    'title' => $activitycompletiontitle,
                    'url' => $activitycompletionlink
                ],
                [
                    'hasuserlinks' => $completionreporttitle,
                    'title' => $completionreporttitle,
                    'url' => $completionreportlink
                ],
                [
                    'hasuserlinks' => $logstitle,
                    'title' => $logstitle,
                    'url' => $logslink
                ],
                [
                    'hasuserlinks' => $livelogstitle,
                    'title' => $livelogstitle,
                    'url' => $livelogslink
                ],
                [
                    'hasuserlinks' => $participationtitle,
                    'title' => $participationtitle,
                    'url' => $participationlink
                ],
                [
                    'hasuserlinks' => $activitytitle,
                    'title' => $activitytitle,
                    'url' => $activitylink
                ],
                [
                    'hasqbanklinks' => $qbanktitle,
                    'title' => $qbanktitle,
                    'url' => $qbanklink
                ],
                [
                    'hasqbanklinks' => $qcattitle,
                    'title' => $qcattitle,
                    'url' => $qcatlink
                ],
                [
                    'hasqbanklinks' => $qimporttitle,
                    'title' => $qimporttitle,
                    'url' => $qimportlink
                ],
                [
                    'hasqbanklinks' => $qexporttitle,
                    'title' => $qexporttitle,
                    'url' => $qexportlink
                ],
                [
                    'hascoursemanagelinks' => $courseedittitle,
                    'title' => $courseedittitle,
                    'url' => $courseeditlink
                ],
                [
                    'hascoursemanagelinks' => $gradestitle,
                    'title' => $gradestitle,
                    'url' => $gradeslink
                ],
                [
                    'hascoursemanagelinks' => $coursecompletiontitle,
                    'title' => $coursecompletiontitle,
                    'url' => $coursecompletionlink
                ],
                [
                    'hascoursemanagelinks' => $hascompetency,
                    'title' => $competencytitle,
                    'url' => $competencyurl
                ],
                [
                    'hascoursemanagelinks' => $courseadmintitle,
                    'title' => $courseadmintitle,
                    'url' => $courseadminlink
                ],
                [
                    'hascoursemanagelinks' => $copycoursetitle,
                    'title' => $copycoursetitle,
                    'url' => $copycourselink
                ],
                [
                    'hascoursemanagelinks' => $courseresettitle,
                    'title' => $courseresettitle,
                    'url' => $courseresetlink
                ],
                [
                    'hascoursemanagelinks' => $coursebackuptitle,
                    'title' => $coursebackuptitle,
                    'url' => $coursebackuplink
                ],
                [
                    'hascoursemanagelinks' => $courserestoretitle,
                    'title' => $courserestoretitle,
                    'url' => $courserestorelink
                ],
                [
                    'hascoursemanagelinks' => $courseimporttitle,
                    'title' => $courseimporttitle,
                    'url' => $courseimportlink
                ],
                [
                    'hascoursemanagelinks' => $recyclebintitle,
                    'title' => $recyclebintitle,
                    'url' => $recyclebinlink
                ],
                [
                    'hascoursemanagelinks' => $filtertitle,
                    'title' => $filtertitle,
                    'url' => $filterlink
                ],
                [
                    'hascoursemanagelinks' => $eventmonitoringtitle,
                    'title' => $eventmonitoringtitle,
                    'url' => $eventmonitoringlink
                ],
                [
                    'hasbadgelinks' => $badgemanagetitle,
                    'title' => $badgemanagetitle,
                    'url' => $badgemanagelink
                ],
                [
                    'hasbadgelinks' => $badgeaddtitle,
                    'title' => $badgeaddtitle,
                    'url' => $badgeaddlink
                ],
            ],
        ];
        // Attach easy enrollment links if active.
        if ($globalhaseasyenrollment && $coursehaseasyenrollment) {
            $dashlinks['dashlinks'][] = [
                'haseasyenrollment' => $coursehaseasyenrollment,
                'title' => $easycodetitle,
                'url' => $easycodelink
            ];
        }
        return $this->render_from_template('theme_fordson/teacherdash', $dashlinks);
    }

    public function courseactivities_menu() {
        global $COURSE, $CFG;
        $menu = new custom_menu();
        $context = $this->page->context;
        if (isset($COURSE->id) && $COURSE->id > 1) {
            $branchtitle = get_string('courseactivities', 'theme_fordson');
            $branchlabel = $branchtitle;
            $branchurl = new moodle_url('#');
            $branch = $menu->add($branchlabel, $branchurl, $branchtitle, 10002);
            $data = theme_fordson_get_course_activities();
            foreach ($data as $modname => $modfullname) {
                if ($modname === 'resources') {
                    $branch->add($modfullname,
                        new moodle_url('/course/resources.php', [
                            'id' => $this->page->course->id
                        ]));
                } else {
                    $branch->add($modfullname,
                        new moodle_url('/mod/' . $modname . '/index.php', [
                            'id' => $this->page->course->id
                        ]));
                }
            }
        }
        return $this->render_courseactivities_menu($menu);
    }

    protected function render_courseactivities_menu(custom_menu $menu) {
        global $CFG;
        $content = '';
        foreach ($menu->get_children() as $item) {
            $context = $item->export_for_template($this);
            $content .= $this->render_from_template('theme_fordson/activitygroups', $context);
        }
        return $content;
    }

    public function footnote() {
        $footnote = '';
        $footnote = (empty($this->page->theme->settings->footnote)) ? false :
            format_text($this->page->theme->settings->footnote);
        return $footnote;
    }

    public function brandorganization_footer() {
        $theme = theme_config::load('fordson');
        $setting = format_string($theme->settings->brandorganization);
        return $setting != '' ? $setting : '';
    }

    public function brandwebsite_footer() {
        $theme = theme_config::load('fordson');
        $setting = $theme->settings->brandwebsite;
        return $setting != '' ? $setting : '';
    }

    public function brandphone_footer() {
        $theme = theme_config::load('fordson');
        $setting = $theme->settings->brandphone;
        return $setting != '' ? $setting : '';
    }

    public function brandemail_footer() {
        $theme = theme_config::load('fordson');
        $setting = $theme->settings->brandemail;
        return $setting != '' ? $setting : '';
    }

    public function logintext_custom() {
        $logintextcustom = (empty($this->page->theme->settings->fptextboxlogout)) ? false :
            format_text($this->page->theme->settings->fptextboxlogout);
        return $logintextcustom;
    }

    public function render_login(login $form) {
        global $SITE;
        $context = $form->export_for_template($this);
        // Override because rendering is not supported in template yet.
        $context->cookieshelpiconformatted = $this->help_icon('cookiesenabled');
        $context->errorformatted = $this->error_text($context->error);
        $url = $this->get_logo_url();
        // Custom logins.
        $context->logintext_custom = format_text($this->page->theme->settings->fptextboxlogout);
        $context->logintopimage = $this->page->theme->setting_file_url('logintopimage', 'logintopimage');
        $context->hascustomlogin = $this->page->theme->settings->showcustomlogin == 1;
        $context->hasdefaultlogin = $this->page->theme->settings->showcustomlogin == 0;
        $context->alertbox = format_text($this->page->theme->settings->alertbox,
            FORMAT_HTML,
            [
                'noclean' => true
            ]);
        if ($url) {
            $url = $url->out(false);
        }
        $context->logourl = $url;
        $context->sitename = format_string($SITE->fullname,
            true,
            ['context' => context_course::instance(SITEID), "escape" => false]);
        return $this->render_from_template('core/loginform', $context);
    }

    public function favicon() {
        $favicon = $this->page->theme->setting_file_url('favicon', 'favicon');

        if (empty($favicon)) {
            return $this->page->theme->image_url('favicon', 'theme');
        } else {
            return $favicon;
        }
    }

    public function display_ilearn_secure_alert() {
        global $DB;

        if (strpos($this->page->url, '/mod/quiz/view.php') === false) {
            return false;
        }

        $cm = $this->page->cm;

        if ($cm) {
            $quiz = $DB->get_record('quiz',
                [
                    'id' => $cm->instance
                ]);
            $globalhasilearnsecureplugin = $DB->get_manager()->table_exists('quizaccess_ilearnbrowser') ? true : false;
        }
        // Turn off alert while taking a quiz.
        if (strpos($this->page->url, '/mod/quiz/attempt.php')) {
            return false;
        }
        if ($cm && $quiz && $globalhasilearnsecureplugin) {
            $quizrecord = $DB->get_record('quizaccess_ilearnbrowser',
                [
                    'quiz_id' => $quiz->id
                ]);
            if ($quizrecord && $quizrecord->browserrequired == 1) {
                return true;
            }
        }
        return false;
    }

    public function show_teacher_navbarcolor() {
        $theme = theme_config::load('fordson');
        $context = $this->page->context;
        $hasteacherrole = has_capability('moodle/course:viewhiddenactivities', $context);

        if ($this->page->theme->settings->navbarcolorswitch == 1 && $hasteacherrole) {
            return true;
        }
        return false;
    }

    public function show_student_navbarcolor() {
        $theme = theme_config::load('fordson');
        $context = $this->page->context;
        $hasstudentrole = !has_capability('moodle/course:viewhiddenactivities', $context);

        if ($this->page->theme->settings->navbarcolorswitch == 1 && $hasstudentrole) {
            return true;
        }
        return false;
    }

}