<?php

if (! class_exists('DUP_PRO_WP_List_Table')) {
    require_once dirname(__FILE__) . '/class.wp.list.table.php';
}

/**
 * List table class
 */
class DUP_PRO_Package_Pagination extends DUP_PRO_WP_List_Table
{
    /**
     * Get num items per page
     *
     * @return int
     */
    public function get_per_page()
    {
        return $this->get_items_per_page('duplicator_pro_opts_per_page');
    }

    /**
     * Display pagination
     *
     * @param int $total_items Total items
     * @param int $per_page    Per page
     *
     * @return void
     */
    public function display_pagination($total_items, $per_page = 10)
    {
        $this->set_pagination_args(array(
            'total_items' => $total_items,
            'per_page'    => $per_page,
        ));
        $which = 'top';
        $this->pagination($which);
    }
}
