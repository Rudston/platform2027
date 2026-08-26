<?php

namespace App\Enums\Polls;

/**
 * How a Rating Scale asks to be drawn.
 *
 * A scale cannot be recognised by its NAME — that is admin-curated display
 * text, and branching on it breaks the moment it is renamed or translated —
 * nor by its shape: the 5-point agreement scale is also five points valued
 * 1..5. So a scale declares its own presentation.
 *
 * Adding a widget is a case here plus a branch in the respond form; the scale
 * data itself never changes.
 */
enum RatingScalePresentation: string
{
    /** A labelled dropdown. The default, and correct for anything wordy. */
    case Select = 'select';

    /** A row of stars, filled left-to-right. Only sensible for a short, ordered, unlabelled scale. */
    case Stars  = 'stars';
}
