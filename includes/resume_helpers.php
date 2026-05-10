<?php
declare(strict_types=1);

/** Max upload size (2 MB). */
const RESUME_MAX_BYTES = 2097152;

/** Allowed extensions and MIME types (whitelist). */
const RESUME_ALLOWED = [
    'pdf'  => ['application/pdf'],
    'doc'  => ['application/msword'],
    'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
];

const RESUME_TYPES = ['general_cv', 'specialization_cv'];

const RESUME_TYPE_COLUMNS = [
    'general_cv'         => 'general_cv',
    'specialization_cv' => 'specialization_cv',
];

function resume_project_root(): string
{
    return dirname(__DIR__);
}

function resume_upload_dir(): string
{
    return resume_project_root() . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'resumes';
}

function resume_type_to_column(string $type): ?string
{
    return RESUME_TYPE_COLUMNS[$type] ?? null;
}

function resume_relative_path(string $filename): string
{
    return 'uploads/resumes/' . $filename;
}

/** Ensure stored path points only inside uploads/resumes (no traversal). */
function resume_is_safe_stored_path(?string $relative): bool
{
    if ($relative === null || $relative === '') {
        return false;
    }
    $normalized = str_replace('\\', '/', $relative);
    if (str_contains($normalized, '..')) {
        return false;
    }
    return str_starts_with($normalized, 'uploads/resumes/');
}

function resume_absolute_from_stored(string $relative): string
{
    return resume_project_root() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
}

/**
 * @return array{ok: true, ext: string}|array{ok: false, message: string}
 */
function resume_validate_uploaded_file(array $file): array
{
    if (!isset($file['error']) || is_array($file['error'])) {
        return ['ok' => false, 'message' => 'Invalid upload.'];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $msg = $file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE
            ? 'File too large.'
            : 'Upload failed.';
        return ['ok' => false, 'message' => $msg];
    }
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return ['ok' => false, 'message' => 'Invalid upload.'];
    }
    if (($file['size'] ?? 0) > RESUME_MAX_BYTES) {
        return ['ok' => false, 'message' => 'File must be 2MB or smaller.'];
    }
    $orig = (string) ($file['name'] ?? '');
    $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    if ($ext === '' || !isset(RESUME_ALLOWED[$ext])) {
        return ['ok' => false, 'message' => 'Only PDF, DOC, and DOCX files are allowed.'];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    if ($mime === false || !in_array($mime, RESUME_ALLOWED[$ext], true)) {
        return ['ok' => false, 'message' => 'Invalid file type.'];
    }

    return ['ok' => true, 'ext' => $ext];
}

function resume_safe_unlink(?string $storedRelative): void
{
    if ($storedRelative === null || $storedRelative === '') {
        return;
    }
    if (!resume_is_safe_stored_path($storedRelative)) {
        return;
    }
    $abs = resume_absolute_from_stored($storedRelative);
    if (is_file($abs)) {
        @unlink($abs);
    }
}
