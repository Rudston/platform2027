# 10: Amending a Poll cannot strand a rating scale

**What to build:** Changing an unanswered Poll from a rating ballot to any other
kind removes its rating scale. Today the scale is silently restored, leaving a
single-choice question carrying one — precisely the combination the service
already refuses to CREATE.

The cause is one operator: the amendment path reads the incoming scale with `??`
where the surrounding code deliberately uses `array_key_exists`, and the comment
two dozen lines above states that rule. The compose form always sends an explicit
null when the shape changes, so the null is discarded and the old value returns.

**Blocked by:** 07 (shared test schema builder).

**Status:** ready-for-agent

- [ ] Switching an unanswered Poll off Rating clears its scale
- [ ] Switching one TO Rating without choosing a scale is still refused
- [ ] A rating Poll that keeps its shape keeps its scale
- [ ] A test asserts the stored question afterwards, not just the absence of an exception
- [ ] Every other field on the amendment path is checked for the same `??` mistake
