<?php

/*
 * Package-owned config, merged under the "church-operations" key.
 *
 * Deliberately minimal: WP 0A item 11 is a loading seam, not a feature. `version`
 * exists so the smoke test can assert the merge actually happened rather than
 * assert against a key that would be null either way.
 */

return [
    'version' => '0.1.0-seam',
];
