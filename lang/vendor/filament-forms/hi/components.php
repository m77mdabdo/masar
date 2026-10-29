<?php

return [
  'builder' => [
    'actions' => [
      'clone' => [
        'label' => 'प्रतिलिपि बनाएँ',
      ],
      'add' => [
        'modal' => [
          'heading' => ':label में जोड़ें',
          'actions' => [
            'add' => [
              'label' => 'जोड़ें',
            ],
          ],
        ],
      ],
      'add_between' => [
        'modal' => [
          'heading' => ':label में जोड़ें',
          'actions' => [
            'add' => [
              'label' => 'जोड़ें',
            ],
          ],
        ],
      ],
      'edit' => [
        'label' => 'संपादित करें',
        'modal' => [
          'heading' => 'ब्लॉक संपादित करें',
          'actions' => [
            'save' => [
              'label' => 'परिवर्तन सहेजें',
            ],
          ],
        ],
      ],
      'reorder' => [
        'label' => 'खिसकाएँ',
      ],
      'collapse' => [
        'label' => 'संक्षिप्त करें',
      ],
      'expand' => [
        'label' => 'विस्तृत करें',
      ],
      'collapse_all' => [
        'label' => 'सभी संक्षिप्त करें',
      ],
      'expand_all' => [
        'label' => 'सभी विस्तृत करें',
      ],
    ],
  ],
  'checkbox_list' => [
    'actions' => [
      'deselect_all' => [
        'label' => 'सभी अचयनित करें',
      ],
      'select_all' => [
        'label' => 'सभी चुनें',
      ],
    ],
  ],
  'color_picker' => [
    'panel_label' => 'रंग चयनकर्ता',
  ],
  'date_time_picker' => [
    'month_select' => [
      'label' => 'महीना',
    ],
    'year_input' => [
      'label' => 'वर्ष',
    ],
    'hour_input' => [
      'label' => 'घंटा',
    ],
    'minute_input' => [
      'label' => 'मिनट',
    ],
    'second_input' => [
      'label' => 'सेकंड',
    ],
  ],
  'file_upload' => [
    'actions' => [
      'download' => [
        'label' => 'डाउनलोड करें',
      ],
      'open' => [
        'label' => 'नए टैब में खोलें',
      ],
    ],
    'editor' => [
      'label' => 'छवि संपादक',
      'actions' => [
        'cancel' => [
          'label' => 'रद्द करें',
        ],
        'drag_crop' => [
          'label' => 'ड्रैग मोड "क्रॉप"',
        ],
        'drag_move' => [
          'label' => 'ड्रैग मोड "खिसकाएँ"',
        ],
        'flip_horizontal' => [
          'label' => 'छवि को क्षैतिज रूप से पलटें',
        ],
        'flip_vertical' => [
          'label' => 'छवि को लंबवत रूप से पलटें',
        ],
        'move_down' => [
          'label' => 'छवि को नीचे खिसकाएँ',
        ],
        'move_left' => [
          'label' => 'छवि को बाईं ओर खिसकाएँ',
        ],
        'move_right' => [
          'label' => 'छवि को दाईं ओर खिसकाएँ',
        ],
        'move_up' => [
          'label' => 'छवि को ऊपर खिसकाएँ',
        ],
        'reset' => [
          'label' => 'रीसेट करें',
        ],
        'rotate_left' => [
          'label' => 'छवि को बाईं ओर घुमाएँ',
        ],
        'rotate_right' => [
          'label' => 'छवि को दाईं ओर घुमाएँ',
        ],
        'set_aspect_ratio' => [
          'label' => 'आस्पेक्ट रेशियो :ratio पर सेट करें',
        ],
        'save' => [
          'label' => 'सहेजें',
        ],
        'zoom_100' => [
          'label' => 'छवि को 100% पर ज़ूम करें',
        ],
        'zoom_in' => [
          'label' => 'ज़ूम इन करें',
        ],
        'zoom_out' => [
          'label' => 'ज़ूम आउट करें',
        ],
      ],
      'fields' => [
        'height' => [
          'label' => 'ऊँचाई',
          'unit' => 'px',
        ],
        'rotation' => [
          'label' => 'घुमाव',
          'unit' => 'डिग्री',
        ],
        'width' => [
          'label' => 'चौड़ाई',
          'unit' => 'px',
        ],
        'x_position' => [
          'label' => 'X',
          'unit' => 'px',
        ],
        'y_position' => [
          'label' => 'Y',
          'unit' => 'px',
        ],
      ],
      'aspect_ratios' => [
        'label' => 'आस्पेक्ट रेशियो',
        'no_fixed' => [
          'label' => 'मुक्त',
        ],
      ],
      'svg' => [
        'messages' => [
          'confirmation' => 'SVG फ़ाइलों को संपादित करने की अनुशंसा नहीं की जाती, क्योंकि स्केल करने पर गुणवत्ता घट सकती है।\\n क्या आप वाकई जारी रखना चाहते हैं?',
          'disabled' => 'SVG फ़ाइलों का संपादन अक्षम है, क्योंकि स्केल करने पर गुणवत्ता घट सकती है।',
        ],
      ],
    ],
  ],
  'key_value' => [
    'actions' => [
      'reorder' => [
        'label' => 'पंक्ति का क्रम बदलें',
      ],
    ],
    'columns' => [
      'actions' => [
        'label' => 'क्रियाएँ',
      ],
      'reorder' => [
        'label' => 'क्रम बदलें',
      ],
    ],
  ],
  'markdown_editor' => [
    'file_attachments_accepted_file_types_message' => 'अपलोड की गई फ़ाइलें इस प्रकार की होनी चाहिए: :values।',
    'file_attachments_max_size_message' => 'अपलोड की गई फ़ाइलें :max किलोबाइट से बड़ी नहीं होनी चाहिए।',
    'tools' => [
      'blockquote' => 'ब्लॉककोट',
      'heading' => 'शीर्षक',
      'redo' => 'दोहराएँ',
      'table' => 'तालिका',
      'undo' => 'पूर्ववत करें',
    ],
  ],
  'modal_table_select' => [
    'actions' => [
      'select' => [
        'label' => 'चुनें',
        'actions' => [
          'select' => [
            'label' => 'चुनें',
          ],
        ],
      ],
    ],
  ],
  'radio' => [
    'boolean' => [
      'true' => 'हाँ',
      'false' => 'नहीं',
    ],
  ],
  'repeater' => [
    'columns' => [
      'actions' => [
        'label' => 'क्रियाएँ',
      ],
      'reorder' => [
        'label' => 'क्रम बदलें',
      ],
    ],
    'actions' => [
      'add_between' => [
        'label' => 'बीच में डालें',
      ],
      'clone' => [
        'label' => 'प्रतिलिपि बनाएँ',
      ],
      'reorder' => [
        'label' => 'खिसकाएँ',
      ],
      'collapse' => [
        'label' => 'संक्षिप्त करें',
      ],
      'expand' => [
        'label' => 'विस्तृत करें',
      ],
      'collapse_all' => [
        'label' => 'सभी संक्षिप्त करें',
      ],
      'expand_all' => [
        'label' => 'सभी विस्तृत करें',
      ],
    ],
  ],
  'rich_editor' => [
    'actions' => [
      'attach_files' => [
        'label' => 'फ़ाइल अपलोड करें',
        'modal' => [
          'heading' => 'फ़ाइल अपलोड करें',
          'form' => [
            'file' => [
              'label' => [
                'new' => 'फ़ाइल',
                'existing' => 'फ़ाइल बदलें',
              ],
            ],
            'alt' => [
              'label' => [
                'new' => 'वैकल्पिक टेक्स्ट',
                'existing' => 'वैकल्पिक टेक्स्ट बदलें',
              ],
            ],
          ],
        ],
      ],
      'custom_block' => [
        'modal' => [
          'actions' => [
            'insert' => [
              'label' => 'डालें',
            ],
            'save' => [
              'label' => 'सहेजें',
            ],
          ],
        ],
      ],
      'grid' => [
        'label' => 'ग्रिड',
        'modal' => [
          'heading' => 'ग्रिड',
          'form' => [
            'preset' => [
              'label' => 'प्रीसेट',
              'placeholder' => 'कोई नहीं',
              'options' => [
                'two' => 'दो',
                'three' => 'तीन',
                'four' => 'चार',
                'five' => 'पाँच',
                'two_start_third' => 'दो (आरंभ में एक-तिहाई)',
                'two_end_third' => 'दो (अंत में एक-तिहाई)',
                'two_start_fourth' => 'दो (आरंभ में एक-चौथाई)',
                'two_end_fourth' => 'दो (अंत में एक-चौथाई)',
              ],
            ],
            'columns' => [
              'label' => 'कॉलम',
            ],
            'from_breakpoint' => [
              'label' => 'ब्रेकपॉइंट से',
              'options' => [
                'default' => 'सभी',
                'sm' => 'छोटा',
                'md' => 'मध्यम',
                'lg' => 'बड़ा',
                'xl' => 'अतिरिक्त बड़ा',
                '2xl' => 'दोगुना अतिरिक्त बड़ा',
              ],
            ],
            'is_asymmetric' => [
              'label' => 'दो असमान कॉलम',
            ],
            'start_span' => [
              'label' => 'आरंभ विस्तार',
            ],
            'end_span' => [
              'label' => 'अंत विस्तार',
            ],
          ],
        ],
      ],
      'link' => [
        'label' => 'लिंक',
        'modal' => [
          'heading' => 'लिंक',
          'form' => [
            'url' => [
              'label' => 'URL',
            ],
            'should_open_in_new_tab' => [
              'label' => 'नए टैब में खोलें',
            ],
          ],
        ],
      ],
      'text_color' => [
        'label' => 'टेक्स्ट का रंग',
        'modal' => [
          'heading' => 'टेक्स्ट का रंग',
          'form' => [
            'color' => [
              'label' => 'रंग',
              'options' => [
                'slate' => 'स्लेटी',
                'gray' => 'धूसर',
                'zinc' => 'ज़िंक',
                'neutral' => 'तटस्थ',
                'stone' => 'पत्थर',
                'mauve' => 'मोव',
                'olive' => 'जैतूनी',
                'mist' => 'धुंध',
                'taupe' => 'टॉप',
                'red' => 'लाल',
                'orange' => 'नारंगी',
                'amber' => 'एम्बर',
                'yellow' => 'पीला',
                'lime' => 'लाइम',
                'green' => 'हरा',
                'emerald' => 'पन्ना',
                'teal' => 'टील',
                'cyan' => 'सियान',
                'sky' => 'आसमानी',
                'blue' => 'नीला',
                'indigo' => 'जामुनी',
                'violet' => 'बैंगनी',
                'purple' => 'परपल',
                'fuchsia' => 'फ़ुशिया',
                'pink' => 'गुलाबी',
                'rose' => 'रोज़',
              ],
            ],
            'custom_color' => [
              'label' => 'कस्टम रंग',
            ],
          ],
        ],
      ],
    ],
    'file_attachments_accepted_file_types_message' => 'अपलोड की गई फ़ाइलें इस प्रकार की होनी चाहिए: :values।',
    'file_attachments_max_size_message' => 'अपलोड की गई फ़ाइलें :max किलोबाइट से बड़ी नहीं होनी चाहिए।',
    'no_merge_tag_search_results_message' => 'कोई मर्ज टैग परिणाम नहीं।',
    'mentions' => [
      'no_options_message' => 'कोई विकल्प उपलब्ध नहीं।',
      'no_search_results_message' => 'आपकी खोज से कोई परिणाम मेल नहीं खाता।',
      'search_prompt' => 'खोजने के लिए लिखना शुरू करें...',
      'searching_message' => 'खोजा जा रहा है...',
    ],
    'toolbar' => [
      'label' => 'संपादक टूलबार',
    ],
    'tools' => [
      'align_center' => 'बीच में संरेखित करें',
      'align_end' => 'अंत में संरेखित करें',
      'align_justify' => 'दोनों ओर संरेखित करें',
      'align_start' => 'आरंभ में संरेखित करें',
      'clear_formatting' => 'फ़ॉर्मेटिंग हटाएँ',
      'code' => 'कोड',
      'custom_blocks' => 'ब्लॉक',
      'details' => 'विवरण',
      'h4' => 'शीर्षक 4',
      'h5' => 'शीर्षक 5',
      'h6' => 'शीर्षक 6',
      'grid' => 'ग्रिड',
      'grid_delete' => 'ग्रिड हटाएँ',
      'highlight' => 'हाइलाइट',
      'horizontal_rule' => 'क्षैतिज रेखा',
      'lead' => 'मुख्य टेक्स्ट',
      'merge_tags' => 'मर्ज टैग',
      'paragraph' => 'अनुच्छेद',
      'small' => 'छोटा टेक्स्ट',
      'subscript' => 'सबस्क्रिप्ट',
      'superscript' => 'सुपरस्क्रिप्ट',
      'table' => 'तालिका',
      'table_delete' => 'तालिका हटाएँ',
      'table_add_column_before' => 'पहले कॉलम जोड़ें',
      'table_add_column_after' => 'बाद में कॉलम जोड़ें',
      'table_delete_column' => 'कॉलम हटाएँ',
      'table_add_row_before' => 'ऊपर पंक्ति जोड़ें',
      'table_add_row_after' => 'नीचे पंक्ति जोड़ें',
      'table_delete_row' => 'पंक्ति हटाएँ',
      'table_merge_cells' => 'सेल मर्ज करें',
      'table_split_cell' => 'सेल विभाजित करें',
      'table_toggle_header_row' => 'शीर्षक पंक्ति टॉगल करें',
      'table_toggle_header_cell' => 'शीर्षक सेल टॉगल करें',
      'text_color' => 'टेक्स्ट का रंग',
      'underline' => 'रेखांकित',
    ],
    'uploading_file_message' => 'फ़ाइल अपलोड हो रही है...',
  ],
  'select' => [
    'actions' => [
      'clear' => [
        'label' => 'चयन हटाएँ',
      ],
      'create_option' => [
        'label' => 'बनाएँ',
        'modal' => [
          'heading' => 'बनाएँ',
          'actions' => [
            'create' => [
              'label' => 'बनाएँ',
            ],
            'create_another' => [
              'label' => 'बनाएँ और एक और बनाएँ',
            ],
          ],
        ],
      ],
      'edit_option' => [
        'label' => 'संपादित करें',
        'modal' => [
          'heading' => 'संपादित करें',
          'actions' => [
            'save' => [
              'label' => 'सहेजें',
            ],
          ],
        ],
      ],
      'remove_option' => [
        'label' => ':label हटाएँ',
      ],
    ],
    'boolean' => [
      'true' => 'हाँ',
      'false' => 'नहीं',
    ],
    'loading_message' => 'लोड हो रहा है...',
    'max_items_message' => 'केवल :count चुने जा सकते हैं।',
    'no_options_message' => 'कोई विकल्प उपलब्ध नहीं।',
    'searching_message' => 'खोजा जा रहा है...',
    'search_label' => 'खोजें',
  ],
  'tags_input' => [
    'actions' => [
      'delete' => [
        'label' => 'हटाएँ',
      ],
    ],
    'tag_added' => 'जोड़ा गया: :tag',
    'tag_removed' => 'हटाया गया: :tag',
  ],
  'text_input' => [
    'actions' => [
      'copy' => [
        'label' => 'कॉपी करें',
        'message' => 'कॉपी किया गया',
      ],
      'hide_password' => [
        'label' => 'पासवर्ड छिपाएँ',
      ],
      'show_password' => [
        'label' => 'पासवर्ड दिखाएँ',
      ],
    ],
  ],
  'toggle_buttons' => [
    'boolean' => [
      'true' => 'हाँ',
      'false' => 'नहीं',
    ],
  ],
];
