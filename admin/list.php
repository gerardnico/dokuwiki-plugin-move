<?php
/**
 * Plugin : Move
 *
 * An admin Dokuwiki plugin to be able to move several pages
 * of a namespace.
 *
 * {@link admin_plugin_move_main} shows a form to move one page or one namespace
 * {@link admin_plugin_move_tree} shows a form in a tree version
 *
 * This admin plugin shows a list of page in a namespace that can be moved
 * together in an other namespace
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Nicolas GERARD <gerardnico@gmail.com>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

/**
 * Admin component of the move plugin. Provides the user interface.
 */
class admin_plugin_move_list extends DokuWiki_Admin_Plugin {

    /** @var helper_plugin_move_plan $plan */
    protected $plan;

    public function __construct() {

        $this->plan = plugin_load('helper', 'move_plan');

    }

    /**
     * @param $language
     * @return string
     */
    public function getMenuText($language) {
        $label = $this->getLang('menu')." by list";
        if($this->plan->isCommited()) $label .= ' '.$this->getLang('inprogress');
        return $label;
//        return false; // do not show in Admin menu
    }


    /**
     * Get the sort number that defines the position in the admin menu.
     *
     * @return int The sort number
     */
    function getMenuSort() {
        return 1011;
    }

    /**
     * If this admin plugin is for admins only
     *
     * @return bool false
     */
    function forAdminOnly() {
        return false;
    }

    /**
     * Handle the input
     */
    function handle() {

        global $INPUT;

        if($INPUT->has('pages')) {
            $pages = $INPUT->arr('pages');
            echo 'List of pages';
            foreach($pages as $page){
                echo $page;
            }
        } else {
            echo 'No Pages parameter';
        }

        // create a new plan if possible and sufficient data was given
//        $this->createPlanFromInput();
//
//        // handle workflow button presses
//        if($this->plan->isCommited()) {
//            helper_plugin_move_rewrite::addLock(); //todo: right place?
//            switch($INPUT->str('ctl')) {
//                case 'continue':
//                    $this->plan->nextStep();
//                    break;
//                case 'skip':
//                    $this->plan->nextStep(true);
//                    break;
//                case 'abort':
//                    $this->plan->abort();
//                    break;
//            }
//        }
    }

    /**
     * Display the interface
     */
    function html() {
        // decide what to do based on the plan's state
        if($this->plan->isCommited()) {
            $this->GUI_progress();
        } else {
            // display form
            $this->GUI_simpleForm();
        }
    }

    /**
     * Get input variables and create a move plan from them
     *
     * @return bool
     */
    protected function createPlanFromInput() {
        global $INPUT;
        global $ID;

        if($this->plan->isCommited()) return false;

        $this->plan->setOption('autoskip', $INPUT->bool('autoskip'));
        $this->plan->setOption('autorewrite', $INPUT->bool('autorewrite'));

        if($ID && $INPUT->has('dst')) {
            $dst = trim($INPUT->str('dst'));
            if($dst == '') {
                msg($this->getLang('nodst'), -1);
                return false;
            }

            // input came from form
            if($INPUT->str('class') == 'namespace') {
                $src = getNS($ID);

                if($INPUT->str('type') == 'both') {
                    $this->plan->addPageNamespaceMove($src, $dst);
                    $this->plan->addMediaNamespaceMove($src, $dst);
                } else if($INPUT->str('type') == 'page') {
                    $this->plan->addPageNamespaceMove($src, $dst);
                } else if($INPUT->str('type') == 'media') {
                    $this->plan->addMediaNamespaceMove($src, $dst);
                }
            } else {
                $this->plan->addPageMove($ID, $INPUT->str('dst'));
            }
            $this->plan->commit();
            return true;
        } elseif($INPUT->has('json')) {
            // input came via JSON from tree manager
            $json = new JSON(JSON_LOOSE_TYPE);
            $data = $json->decode($INPUT->str('json'));

            foreach((array) $data as $entry) {
                if($entry['class'] == 'ns') {
                    if($entry['type'] == 'page') {
                        $this->plan->addPageNamespaceMove($entry['src'], $entry['dst']);
                    } elseif($entry['type'] == 'media') {
                        $this->plan->addMediaNamespaceMove($entry['src'], $entry['dst']);
                    }
                } elseif($entry['class'] == 'doc') {
                    if($entry['type'] == 'page') {
                        $this->plan->addPageMove($entry['src'], $entry['dst']);
                    } elseif($entry['type'] == 'media') {
                        $this->plan->addMediaMove($entry['src'], $entry['dst']);
                    }
                }
            }

            $this->plan->commit();
            return true;
        }

        return false;
    }

    /**
     * Display the simple move form
     */
    protected function GUI_simpleForm() {
        global $ID;

        echo $this->locale_xhtml('list');

        $treelink = wl($ID, array('do'=>'admin', 'page'=>'move_tree'));
        $mainlink = wl($ID, array('do'=>'admin', 'page'=>'move_main'));
        echo '<p id="plugin_move__treelink">';
        printf($this->getLang('treelink'), $treelink);
        printf(" or the <a href=\"%s\">simple move manager</a>.",$mainlink);
        echo '</p>';

        echo '<noscript><div class="error">' . $this->getLang('noscript') . '</div></noscript>';

        echo '<div id="plugin_move__list">';

        echo '<div class="tree_root tree_pages">';
        echo '<h3>' . $this->getLang('move_pages') . '</h3>';
//        $this->htmlTree(self::TYPE_PAGES);
        echo '</div>';


        /** @var helper_plugin_move_plan $plan */
        $plan = plugin_load('helper', 'move_plan');
        echo '<div class="controls">';
        if($plan->isCommited()) {
            echo '<div class="error">' . $this->getLang('moveinprogress') . '</div>';
        } else {
            $form = new Doku_Form(array('action' => wl($ID), 'id' => 'plugin_move__tree_execute'));
            $form->addHidden('id', $ID);
            $form->addHidden('page', 'move_list');
            $form->addHidden('json', '');

            $nameSpacePath = getNS($ID); // The complete path to the directory
            $pagesOfNamespace = $this->getNamespaceChildren($nameSpacePath);
            foreach ($pagesOfNamespace as $page) {
                $pageId = $page['id'];

                // Title
                if (useHeading('navigation')) {
                    // get page title
                    $title = $page['title'];
                } else {
                    $title = noNSorNS($pageId);
                }

                // Checked or not
                if ($ID == $pageId) {
                    $checked = array('checked' => 'checked');
                } else {
                    $checked = array();
                }
                $form->addElement(form_makeCheckboxField('pages[]', $pageId, $title, $pageId, '', $checked));
                $form->addElement('<br />');
            }
            $form->addElement('<br />');
            $form->addElement('<h3>Options' . $this->getLang('move_pages') . '</h3>');
            $form->addElement(form_makeCheckboxField('autoskip', '1', $this->getLang('autoskip'), '', '', ($this->getConf('autoskip') ? array('checked' => 'checked') : array())));
            $form->addElement('<br />');
            $form->addElement(form_makeCheckboxField('autorewrite', '1', $this->getLang('autorewrite'), '', '', ($this->getConf('autorewrite') ? array('checked' => 'checked') : array())));
            $form->addElement('<br />');
            $form->addElement('<br />');
            $form->addElement(form_makeButton('submit', 'admin', $this->getLang('btn_start')));
            $form->printForm();
        }
        echo '</div>';

        echo '</div>';
    }

    /**
     * Display the GUI while the move progresses
     */
    protected function GUI_progress() {
        echo '<div id="plugin_move__progress">';

        echo $this->locale_xhtml('progress');

        $progress = $this->plan->getProgress();

        if(!$this->plan->inProgress()) {
            echo '<div id="plugin_move__preview">';
            echo '<p>';
            echo '<strong>' . $this->getLang('intro') . '</strong> ';
            echo '<span>' . $this->getLang('preview') . '</span>';
            echo '</p>';
            echo $this->plan->previewHTML();
            echo '</div>';

        }

        echo '<div class="progress" data-progress="' . $progress . '">' . $progress . '%</div>';

        echo '<div class="output">';
        if($this->plan->getLastError()) {
            echo '<p><div class="error">' . $this->plan->getLastError() . '</div></p>';
        } elseif ($this->plan->inProgress()) {
            echo '<p><div class="info">' . $this->getLang('inexecution') . '</div></p>';
        }
        echo '</div>';

        // display all buttons but toggle visibility according to state
        echo '<p></p>';
        echo '<div class="controls">';
        echo '<img src="' . DOKU_BASE . 'lib/images/throbber.gif" class="hide" />';
        $this->btn('start', !$this->plan->inProgress());
        $this->btn('retry', $this->plan->getLastError());
        $this->btn('skip', $this->plan->getLastError());
        $this->btn('continue', $this->plan->inProgress() && !$this->plan->getLastError());
        $this->btn('abort');
        echo '</div>';

        echo '</div>';
    }

    /**
     * Display a move workflow button
     *
     * continue, start, retry - continue next steps
     * abort - abort the whole move
     * skip - skip error and continue
     *
     * @param string $control
     * @param bool   $show should this control be visible?
     */
    protected function btn($control, $show = true) {
        global $ID;

        $skip  = 0;
        $label = $this->getLang('btn_' . $control);
        $id    = $control;
        if($control == 'start') $control = 'continue';
        if($control == 'retry') {
            $control = 'continue';
            $skip    = 0;
        }

        $class = 'move__control ctlfrm-' . $id;
        if(!$show) $class .= ' hide';

        $form = new Doku_Form(array('action' => wl($ID), 'method' => 'post', 'class' => $class));
        $form->addHidden('page', 'move_main');
        $form->addHidden('id', $ID);
        $form->addHidden('ctl', $control);
        $form->addHidden('skip', $skip);
        $form->addElement(form_makeButton('submit', 'admin', $label, array('class' => 'btn ctl-' . $control)));
        $form->printForm();
    }

    /**
     * Return all pages of a namespace (ie directory)
     * Adapted from feed.php and copied from the minimap plugin (syntax_plugin_minimap_minisyntax class)
     *
     * @param $namespace The container of the pages
     * @return array An array of the pages for the namespace
     */
    function getNamespaceChildren($namespace)
    {
        require_once(DOKU_INC . 'inc/search.php');
        global $conf;

        $ns = ':' . cleanID($namespace);
        // ns as a path
        $ns = utf8_encodeFN(str_replace(':', '/', $ns));

        $data = array();

        // Options of the callback function search_universal
        // in the search.php file
        $search_opts = array(
            'depth' => 1,
            'pagesonly' => true,
            'listfiles' => true,
            'listdirs' => false,
            'firsthead' => true
        );
        // search_universal is a function in inc/search.php that accepts the $search_opts parameters
        search($data, $conf['datadir'], 'search_universal', $search_opts, $ns, $lvl = 1, $sort = 'natural');

        return $data;
    }
}
