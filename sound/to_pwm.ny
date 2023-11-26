(setf bias (snd-avg *track* 20 20 op-average))
(osc-pulse 5000 bias)