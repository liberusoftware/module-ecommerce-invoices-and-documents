<?php

return [
    'seams' => [
        // Where a sale is read, once, at draft. Unbound refuses to draft rather
        // than inventing a line.
        'sale' => null,

        // Where a render model becomes a file. Unbound means no file; the
        // module still issues, numbers, stores and lists.
        'renderer' => null,

        // Where a document is transmitted. Unbound means the attempt is
        // recorded and the transmission refused.
        'transport' => null,
    ],

    // What a redacted name or address becomes. Money never changes.
    'redaction_token' => 'redacted',

    // How long an issued document must survive a subject's erasure request.
    // Null is not zero: it is a host that never said, and erasure refuses.
    'retention' => [
        'years' => null,
    ],
];
