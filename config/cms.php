<?php

$definitions = [
    'hero' => ['label' => 'Hero', 'category' => 'Marketing', 'defaults' => ['eyebrow' => '', 'heading' => '', 'highlighted_text' => '', 'description' => '', 'primary_label' => '', 'primary_url' => '', 'secondary_label' => '', 'secondary_url' => '', 'image_url' => '', 'video_url' => '', 'alignment' => 'center', 'background' => 'light']],
    'logo_cloud' => ['label' => 'Logo Cloud', 'category' => 'Social proof', 'defaults' => ['heading' => '', 'items' => []]],
    'feature_grid' => ['label' => 'Feature Grid', 'category' => 'Features', 'defaults' => ['heading' => '', 'description' => '', 'items' => []]],
    'feature_list' => ['label' => 'Feature List', 'category' => 'Features', 'defaults' => ['heading' => '', 'description' => '', 'items' => []]],
    'feature_showcase' => ['label' => 'Feature Showcase', 'category' => 'Features', 'defaults' => ['heading' => '', 'description' => '', 'image_url' => '', 'items' => []]],
    'image_text' => ['label' => 'Image + Text', 'category' => 'Content', 'defaults' => ['heading' => '', 'description' => '', 'image_url' => '', 'image_position' => 'right', 'button_label' => '', 'button_url' => '']],
    'stats' => ['label' => 'Stats', 'category' => 'Social proof', 'defaults' => ['heading' => '', 'items' => []]],
    'testimonials' => ['label' => 'Testimonials', 'category' => 'Social proof', 'defaults' => ['heading' => '', 'items' => []]],
    'pricing' => ['label' => 'Pricing', 'category' => 'Conversion', 'defaults' => ['heading' => 'Simple pricing', 'description' => '', 'data_source' => 'live_plans', 'show_billing_toggle' => true, 'default_interval' => 'monthly', 'highlighted_plan' => '', 'cta_label' => 'Start free trial', 'items' => []]],
    'comparison_table' => ['label' => 'Comparison Table', 'category' => 'Conversion', 'defaults' => ['heading' => '', 'columns' => [], 'rows' => []]],
    'integrations' => ['label' => 'Integrations', 'category' => 'Features', 'defaults' => ['heading' => '', 'description' => '', 'items' => []]],
    'how_it_works' => ['label' => 'How It Works', 'category' => 'Content', 'defaults' => ['heading' => '', 'steps' => []]],
    'faq' => ['label' => 'FAQ', 'category' => 'Content', 'defaults' => ['heading' => 'Frequently asked questions', 'description' => '', 'items' => []]],
    'cta' => ['label' => 'CTA', 'category' => 'Conversion', 'defaults' => ['heading' => '', 'description' => '', 'primary_label' => '', 'primary_url' => '', 'secondary_label' => '', 'secondary_url' => '', 'background' => 'brand']],
    'newsletter' => ['label' => 'Newsletter', 'category' => 'Conversion', 'defaults' => ['heading' => '', 'description' => '', 'button_label' => 'Subscribe', 'form_slug' => 'newsletter']],
    'contact_form' => ['label' => 'Contact Form', 'category' => 'Conversion', 'defaults' => ['heading' => '', 'description' => '', 'form_slug' => 'general-contact']],
    'video' => ['label' => 'Video', 'category' => 'Media', 'defaults' => ['heading' => '', 'url' => '', 'poster_url' => '']],
    'gallery' => ['label' => 'Gallery', 'category' => 'Media', 'defaults' => ['heading' => '', 'items' => []]],
    'rich_text' => ['label' => 'Rich Text', 'category' => 'Content', 'defaults' => ['html' => '']],
    'spacer' => ['label' => 'Spacer', 'category' => 'Layout', 'defaults' => ['size' => 'medium']],
    'custom_html' => ['label' => 'Custom HTML', 'category' => 'Advanced', 'defaults' => ['html' => ''], 'permission' => 'website.custom_html'],
];

return ['blocks' => $definitions];
