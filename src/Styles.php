<?php

namespace App;

class Styles
{
    public const STYLES = [
        'APA 7' => [
            'font_family'              => 'Times New Roman',
            'font_size'                => 12,
            'margin_inches'            => 1.0,
            'page_numbers'             => [
                'position'             => 'top_right',
                'include_on_title_page'=> true,
                'format'               => 'number_only',
            ],
            'title_page_layout'        => 'apa_student',
            'references_label'         => 'References',
            'references_bold'          => true,
            'references_double_spaced' => true,
            'hanging_indent_inches'    => 0.5,
            'body_first_line_indent_inches' => 0.5,
            'citation_name'            => 'APA 7th Edition',
            'in_text_example'          => '(Author, Year) or (Author, Year, p. #) — e.g., (Smith, 2023, p. 14)',
        ],
        'MLA 9' => [
            'font_family'              => 'Times New Roman',
            'font_size'                => 12,
            'margin_inches'            => 1.0,
            'page_numbers'             => [
                'position'             => 'top_right',
                'include_on_title_page'=> false,
                'format'               => 'lastname_page',
            ],
            'title_page_layout'        => 'mla_firstpage',
            'references_label'         => 'Works Cited',
            'references_bold'          => false,
            'references_double_spaced' => true,
            'hanging_indent_inches'    => 0.5,
            'body_first_line_indent_inches' => 0.5,
            'citation_name'            => 'MLA 9th Edition',
            'in_text_example'          => '(Author Page#) — no comma, e.g., (Smith 23)',
        ],
        'Chicago Author-Date' => [
            'font_family'              => 'Times New Roman',
            'font_size'                => 12,
            'margin_inches'            => 1.0,
            'page_numbers'             => [
                'position'             => 'top_right',
                'include_on_title_page'=> false,
                'format'               => 'number_only',
            ],
            'title_page_layout'        => 'chicago',
            'references_label'         => 'References',
            'references_bold'          => false,
            'references_double_spaced' => false,
            'hanging_indent_inches'    => 0.5,
            'body_first_line_indent_inches' => 0.5,
            'citation_name'            => 'Chicago Author-Date',
            'in_text_example'          => '(Author Year, Page#) — e.g., (Smith 2023, 14)',
        ],
        'Harvard' => [
            'font_family'              => 'Times New Roman',
            'font_size'                => 12,
            'margin_inches'            => 1.0,
            'page_numbers'             => [
                'position'             => 'top_right',
                'include_on_title_page'=> true,
                'format'               => 'number_only',
            ],
            'title_page_layout'        => 'harvard',
            'references_label'         => 'Reference List',
            'references_bold'          => false,
            'references_double_spaced' => true,
            'hanging_indent_inches'    => 0.5,
            'body_first_line_indent_inches' => 0.5,
            'citation_name'            => 'Harvard',
            'in_text_example'          => '(Author Year, p. #) — e.g., (Smith 2023, p. 14)',
        ],
    ];
}
