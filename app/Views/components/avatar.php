<?php
/**
 * Componente: avatar.php
 *
 * - string $avatarInitials
 * - string $avatarSize   sm|md|lg  (default md)
 * - string $avatarTone   primary|dark|success  (default primary)
 * - string $avatarSrc    opcional, URL de imagen
 * - string $avatarAlt    opcional
 * - string $avatarId     opcional, id del elemento
 */
$avatarInitials = $avatarInitials ?? '';
$avatarSize     = $avatarSize ?? 'md';
$avatarTone     = $avatarTone ?? 'primary';
$avatarSrc      = $avatarSrc ?? '';
$avatarAlt      = $avatarAlt ?? '';
$avatarId       = $avatarId ?? '';
?>
<span class="avatar avatar--<?php echo htmlspecialchars($avatarSize); ?> avatar--<?php echo htmlspecialchars($avatarTone); ?>"<?php echo $avatarId !== '' ? ' id="' . htmlspecialchars($avatarId) . '"' : ''; ?> data-avatar-initials="<?php echo htmlspecialchars($avatarInitials); ?>" aria-hidden="<?php echo $avatarSrc === '' ? 'true' : 'false'; ?>">
  <?php if ($avatarSrc !== ''): ?>
    <img src="<?php echo htmlspecialchars($avatarSrc); ?>" alt="<?php echo htmlspecialchars($avatarAlt); ?>">
  <?php else: ?>
    <?php echo htmlspecialchars($avatarInitials); ?>
  <?php endif; ?>
</span>
<?php
unset($avatarInitials, $avatarSize, $avatarTone, $avatarSrc, $avatarAlt, $avatarId);
?>
