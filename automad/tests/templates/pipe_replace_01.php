@{ var | def ('Some (test) string') | replace ('/\\((\\w+)\\)/', '<div class="test">$1</div>') }