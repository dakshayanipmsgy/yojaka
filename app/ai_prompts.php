<?php
// Prompt helpers for AI-assisted drafting and summaries

function build_ai_prompt_for_drafting(array $context): string
{
    $subject = $context['subject'] ?? '';
    $recipient = $context['recipient'] ?? ($context['recipient_name'] ?? '');
    $module = $context['module'] ?? 'document';
    $purpose = $context['purpose'] ?? '';
    $office = $context['office_name'] ?? '';
    return "Draft a {$module} for {$office} about {$subject}. Recipient: {$recipient}. Purpose: {$purpose}. Keep it concise.";
}

function build_ai_prompt_for_summary_short(array $context): string
{
    $module = $context['module'] ?? 'document';
    return "Provide a 2-3 sentence short summary for a {$module}.";
}

function build_ai_prompt_for_summary_long(array $context): string
{
    $module = $context['module'] ?? 'document';
    return "Provide a detailed summary with bullet points for a {$module}, keeping key decisions and timelines.";
}
