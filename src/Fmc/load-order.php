<?php
/**
 * Ordered load list for the modular FMC training application.
 *
 * Single source of truth: `bootstrap.php` requires these files for tests and
 * the dev environment, and `build/pack-snippet.php` inlines them in this same
 * order into the Code Snippets bundle. A new file under `src/Fmc/` must be
 * added here or the bundler fails.
 *
 * Paths are relative to this directory. Order matters: a class must come after
 * everything it extends or uses at load time, and `App.php` goes last. The
 * first entry has to start with its `namespace` statement: the bundler injects
 * the FMC_BUNDLE_LOADED guard right after it.
 *
 * @package Fmc
 */

return array(
	'Meta/MetaTypes.php',
	'Meta/DesignMetaKeys.php',
	'Meta/ActionMetaKeys.php',
	'Meta/SpeakerMetaKeys.php',
	'Meta/IncidentMetaKeys.php',
	'Meta/MetaRegistration.php',
	'Domain/SchoolYear.php',
	'PostType/PostTypes.php',
	'Taxonomy/Taxonomies.php',
	'PublicFront/Assets.php',
	'PublicFront/Fields.php',
	'PublicFront/Documents.php',
	'PublicFront/Lists.php',
	'PublicFront/Calendar.php',
	'PublicFront/Editor.php',
	'PublicFront/Screen.php',
	'App.php',
);
