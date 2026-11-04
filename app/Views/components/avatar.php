<?php
/**
 * Componente: avatar.php
 *
 * - string $avatarInitials
 * - string $avatarSize   sm|md|lg|xl  (default md)
 * - string $avatarTone   primary|dark|success  (default primary)
 * - string $avatarSrc    opcional, URL de imagen
 * - string $avatarAlt    opcional
 * - string $avatarId     opcional, id del elemento
 */
$avatarInitials = $avatarInitials ?? '';
$avatarSize     = $avatarSize ?? 'md';
$avatarTone     = $avatarTone ?? 'primary';
$avatarSrc      = trim((string) ($avatarSrc ?? ''));
$avatarAlt      = $avatarAlt ?? '';
$avatarId       = $avatarId ?? '';

// Resolver URL pública si $avatarSrc es una ruta relativa local
if ($avatarSrc !== '' && !str_starts_with($avatarSrc, 'http://') && !str_starts_with($avatarSrc, 'https://') && !str_starts_with($avatarSrc, 'data:')) {
    $baseUrl = defined('BASE_URL') ? rtrim((string) constant('BASE_URL'), '/') : '';
    $srcLimpia = '/' . ltrim(str_replace('\\', '/', $avatarSrc), '/');
    if ($baseUrl !== '' && !str_starts_with($srcLimpia, $baseUrl . '/')) {
        $avatarSrc = $baseUrl . $srcLimpia;
    } else {
        $avatarSrc = $srcLimpia;
    }
}
?>
<span class="avatar avatar--<?php echo htmlspecialchars($avatarSize); ?> avatar--<?php echo htmlspecialchars($avatarTone); ?>"<?php echo $avatarId !== '' ? ' id="' . htmlspecialchars($avatarId) . '"' : ''; ?> data-avatar-initials="<?php echo htmlspecialchars($avatarInitials); ?>" aria-hidden="<?php echo $avatarSrc === '' ? 'true' : 'false'; ?>">
  <?php if ($avatarSrc !== ''): ?>
    <img src="<?php echo htmlspecialchars($avatarSrc); ?>" alt="<?php echo htmlspecialchars($avatarAlt); ?>" onerror="this.style.display='none'; if(this.parentElement){this.parentElement.textContent=this.parentElement.getAttribute('data-avatar-initials') || '<?php echo htmlspecialchars($avatarInitials); ?>';}">
  <?php else: ?>
    <?php echo htmlspecialchars($avatarInitials); ?>
  <?php endif; ?>
</span>
<?php
unset($avatarInitials, $avatarSize, $avatarTone, $avatarSrc, $avatarAlt, $avatarId);
?>
