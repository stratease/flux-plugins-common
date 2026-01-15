import React from 'react';
import {
  Typography,
  Box,
  Card,
  CardContent,
  CardActions,
  TextField,
  Button,
  Select,
  MenuItem,
  FormControl,
  InputLabel,
  Checkbox,
  FormControlLabel,
  Radio,
  RadioGroup,
  Switch,
  Slider,
  Chip,
  Alert,
  Accordion,
  AccordionSummary,
  AccordionDetails,
  Tabs,
  Tab,
  Paper,
  Stack,
  Grid,
  Divider,
  List,
  ListItem,
  ListItemText,
  ListItemIcon,
  ListItemButton,
  IconButton,
  Badge,
  CircularProgress,
  LinearProgress,
  Tooltip,
  Dialog,
  DialogTitle,
  DialogContent,
  DialogActions,
  Autocomplete,
  Rating,
  Pagination,
  Breadcrumbs,
  Link,
  Stepper,
  Step,
  StepLabel,
  Avatar,
  Skeleton,
} from '@mui/material';
import {
  ExpandMore,
  Home,
  Person,
  Settings,
  Favorite,
  Star,
  CheckCircle,
  Info,
  Warning,
  Error as ErrorIcon,
} from '@mui/icons-material';
import { __ } from '@wordpress/i18n';

/**
 * Style Showcase Component
 * 
 * A comprehensive showcase of MUI components for troubleshooting styles.
 * Accessible via hash route on the Logs page: #/style-showcase
 * 
 * @since 1.0.0
 */
const StyleShowcase = () => {
  const [tabValue, setTabValue] = React.useState(0);
  const [dialogOpen, setDialogOpen] = React.useState(false);
  const [sliderValue, setSliderValue] = React.useState(30);
  const [ratingValue, setRatingValue] = React.useState(3);

  return (
    <Box sx={{ p: 3 }}>
      <Typography variant="h4" gutterBottom>
        {__('MUI Component Style Showcase', 'flux-plugins-common')}
      </Typography>
      <Typography variant="body2" color="text.secondary" sx={{ mb: 4 }}>
        {__('This page displays all Material UI components for troubleshooting style conflicts with WordPress admin CSS.', 'flux-plugins-common')}
      </Typography>

      {/* Typography */}
      <Card sx={{ mb: 3 }}>
        <CardContent>
          <Typography variant="h6" gutterBottom>
            {__('Typography', 'flux-plugins-common')}
          </Typography>
          <Stack spacing={2}>
            <Typography variant="h1">Heading 1 - H1</Typography>
            <Typography variant="h2">Heading 2 - H2</Typography>
            <Typography variant="h3">Heading 3 - H3</Typography>
            <Typography variant="h4">Heading 4 - H4</Typography>
            <Typography variant="h5">Heading 5 - H5</Typography>
            <Typography variant="h6">Heading 6 - H6</Typography>
            <Typography variant="body1">Body 1 - Regular paragraph text</Typography>
            <Typography variant="body2">Body 2 - Smaller body text</Typography>
            <Typography variant="caption">Caption - Small helper text</Typography>
            <Typography variant="overline">Overline - Text with overline</Typography>
          </Stack>
        </CardContent>
      </Card>

      {/* Forms */}
      <Card sx={{ mb: 3 }}>
        <CardContent>
          <Typography variant="h6" gutterBottom>
            {__('Form Components', 'flux-plugins-common')}
          </Typography>
          <Stack spacing={3}>
            <TextField
              fullWidth
              label={__('Text Field', 'flux-plugins-common')}
              placeholder={__('Enter text here...', 'flux-plugins-common')}
              helperText={__('Helper text for text field', 'flux-plugins-common')}
            />
            <TextField
              fullWidth
              label={__('Password Field', 'flux-plugins-common')}
              type="password"
              helperText={__('Password helper text', 'flux-plugins-common')}
            />
            <TextField
              fullWidth
              label={__('Email Field', 'flux-plugins-common')}
              type="email"
              helperText={__('Email helper text', 'flux-plugins-common')}
            />
            <TextField
              fullWidth
              multiline
              rows={4}
              label={__('Textarea', 'flux-plugins-common')}
              placeholder={__('Multi-line text input...', 'flux-plugins-common')}
            />
            <FormControl fullWidth>
              <InputLabel>{__('Select Dropdown', 'flux-plugins-common')}</InputLabel>
              <Select label={__('Select Dropdown', 'flux-plugins-common')} defaultValue="">
                <MenuItem value="">{__('None', 'flux-plugins-common')}</MenuItem>
                <MenuItem value="option1">{__('Option 1', 'flux-plugins-common')}</MenuItem>
                <MenuItem value="option2">{__('Option 2', 'flux-plugins-common')}</MenuItem>
                <MenuItem value="option3">{__('Option 3', 'flux-plugins-common')}</MenuItem>
              </Select>
            </FormControl>
            <Autocomplete
              options={['Option A', 'Option B', 'Option C']}
              renderInput={(params) => <TextField {...params} label={__('Autocomplete', 'flux-plugins-common')} />}
            />
            <FormControlLabel
              control={<Checkbox defaultChecked />}
              label={__('Checkbox Option', 'flux-plugins-common')}
            />
            <FormControl>
              <RadioGroup defaultValue="option1">
                <FormControlLabel value="option1" control={<Radio />} label={__('Radio Option 1', 'flux-plugins-common')} />
                <FormControlLabel value="option2" control={<Radio />} label={__('Radio Option 2', 'flux-plugins-common')} />
              </RadioGroup>
            </FormControl>
            <FormControlLabel
              control={<Switch defaultChecked />}
              label={__('Switch Toggle', 'flux-plugins-common')}
            />
            <Box>
              <Typography gutterBottom>{__('Slider', 'flux-plugins-common')}: {sliderValue}</Typography>
              <Slider value={sliderValue} onChange={(e, val) => setSliderValue(val)} />
            </Box>
            <Box>
              <Typography component="label">{__('Rating', 'flux-plugins-common')}</Typography>
              <Rating value={ratingValue} onChange={(e, val) => setRatingValue(val)} />
            </Box>
          </Stack>
        </CardContent>
      </Card>

      {/* Buttons */}
      <Card sx={{ mb: 3 }}>
        <CardContent>
          <Typography variant="h6" gutterBottom>
            {__('Buttons', 'flux-plugins-common')}
          </Typography>
          <Stack spacing={2} direction="row" flexWrap="wrap">
            <Button variant="contained">{__('Contained', 'flux-plugins-common')}</Button>
            <Button variant="outlined">{__('Outlined', 'flux-plugins-common')}</Button>
            <Button variant="text">{__('Text', 'flux-plugins-common')}</Button>
            <Button variant="contained" color="secondary">{__('Secondary', 'flux-plugins-common')}</Button>
            <Button variant="contained" disabled>{__('Disabled', 'flux-plugins-common')}</Button>
            <IconButton color="primary"><Star /></IconButton>
            <IconButton color="secondary"><Favorite /></IconButton>
            <IconButton disabled><Settings /></IconButton>
          </Stack>
        </CardContent>
      </Card>

      {/* Cards */}
      <Grid container spacing={2} sx={{ mb: 3 }}>
        <Grid item xs={12} md={4}>
          <Card>
            <CardContent>
              <Typography variant="h6">{__('Card Title', 'flux-plugins-common')}</Typography>
              <Typography variant="body2" color="text.secondary">
                {__('Card content with description text', 'flux-plugins-common')}
              </Typography>
            </CardContent>
            <CardActions>
              <Button size="small">{__('Action', 'flux-plugins-common')}</Button>
            </CardActions>
          </Card>
        </Grid>
        <Grid item xs={12} md={4}>
          <Card variant="outlined">
            <CardContent>
              <Typography variant="h6">{__('Outlined Card', 'flux-plugins-common')}</Typography>
              <Typography variant="body2" color="text.secondary">
                {__('Card with outlined variant', 'flux-plugins-common')}
              </Typography>
            </CardContent>
          </Card>
        </Grid>
        <Grid item xs={12} md={4}>
          <Paper elevation={3} sx={{ p: 2 }}>
            <Typography variant="h6">{__('Paper Component', 'flux-plugins-common')}</Typography>
            <Typography variant="body2">{__('Paper with elevation', 'flux-plugins-common')}</Typography>
          </Paper>
        </Grid>
      </Grid>

      {/* Alerts */}
      <Card sx={{ mb: 3 }}>
        <CardContent>
          <Typography variant="h6" gutterBottom>
            {__('Alerts', 'flux-plugins-common')}
          </Typography>
          <Stack spacing={2}>
            <Alert severity="success">{__('Success alert message', 'flux-plugins-common')}</Alert>
            <Alert severity="info">{__('Info alert message', 'flux-plugins-common')}</Alert>
            <Alert severity="warning">{__('Warning alert message', 'flux-plugins-common')}</Alert>
            <Alert severity="error">{__('Error alert message', 'flux-plugins-common')}</Alert>
          </Stack>
        </CardContent>
      </Card>

      {/* Chips & Badges */}
      <Card sx={{ mb: 3 }}>
        <CardContent>
          <Typography variant="h6" gutterBottom>
            {__('Chips & Badges', 'flux-plugins-common')}
          </Typography>
          <Stack spacing={2} direction="row" flexWrap="wrap">
            <Chip label={__('Default', 'flux-plugins-common')} />
            <Chip label={__('Primary', 'flux-plugins-common')} color="primary" />
            <Chip label={__('Secondary', 'flux-plugins-common')} color="secondary" />
            <Chip label={__('Success', 'flux-plugins-common')} color="success" />
            <Chip label={__('Error', 'flux-plugins-common')} color="error" />
            <Badge badgeContent={4} color="primary">
              <Favorite />
            </Badge>
            <Badge badgeContent={99} color="error">
              <ErrorIcon />
            </Badge>
          </Stack>
        </CardContent>
      </Card>

      {/* Progress Indicators */}
      <Card sx={{ mb: 3 }}>
        <CardContent>
          <Typography variant="h6" gutterBottom>
            {__('Progress Indicators', 'flux-plugins-common')}
          </Typography>
          <Stack spacing={3}>
            <Box>
              <CircularProgress />
              <CircularProgress color="secondary" sx={{ ml: 2 }} />
            </Box>
            <LinearProgress />
            <LinearProgress color="secondary" />
          </Stack>
        </CardContent>
      </Card>

      {/* Tabs */}
      <Card sx={{ mb: 3 }}>
        <CardContent>
          <Typography variant="h6" gutterBottom>
            {__('Tabs', 'flux-plugins-common')}
          </Typography>
          <Tabs value={tabValue} onChange={(e, v) => setTabValue(v)}>
            <Tab label={__('Tab One', 'flux-plugins-common')} />
            <Tab label={__('Tab Two', 'flux-plugins-common')} />
            <Tab label={__('Tab Three', 'flux-plugins-common')} />
          </Tabs>
          <Box sx={{ mt: 2 }}>
            {tabValue === 0 && <Typography>{__('Content for tab one', 'flux-plugins-common')}</Typography>}
            {tabValue === 1 && <Typography>{__('Content for tab two', 'flux-plugins-common')}</Typography>}
            {tabValue === 2 && <Typography>{__('Content for tab three', 'flux-plugins-common')}</Typography>}
          </Box>
        </CardContent>
      </Card>

      {/* Accordion */}
      <Card sx={{ mb: 3 }}>
        <CardContent>
          <Typography variant="h6" gutterBottom>
            {__('Accordion', 'flux-plugins-common')}
          </Typography>
          <Accordion>
            <AccordionSummary expandIcon={<ExpandMore />}>
              <Typography>{__('Accordion Item 1', 'flux-plugins-common')}</Typography>
            </AccordionSummary>
            <AccordionDetails>
              <Typography>{__('Accordion content goes here', 'flux-plugins-common')}</Typography>
            </AccordionDetails>
          </Accordion>
          <Accordion>
            <AccordionSummary expandIcon={<ExpandMore />}>
              <Typography>{__('Accordion Item 2', 'flux-plugins-common')}</Typography>
            </AccordionSummary>
            <AccordionDetails>
              <Typography>{__('More accordion content', 'flux-plugins-common')}</Typography>
            </AccordionDetails>
          </Accordion>
        </CardContent>
      </Card>

      {/* Lists */}
      <Card sx={{ mb: 3 }}>
        <CardContent>
          <Typography variant="h6" gutterBottom>
            {__('Lists', 'flux-plugins-common')}
          </Typography>
          <List>
            <ListItem>
              <ListItemIcon><Home /></ListItemIcon>
              <ListItemText primary={__('List Item 1', 'flux-plugins-common')} secondary={__('Secondary text', 'flux-plugins-common')} />
            </ListItem>
            <ListItem>
              <ListItemIcon><Person /></ListItemIcon>
              <ListItemText primary={__('List Item 2', 'flux-plugins-common')} />
            </ListItem>
            <ListItemButton>
              <ListItemIcon><Settings /></ListItemIcon>
              <ListItemText primary={__('Clickable List Item', 'flux-plugins-common')} />
            </ListItemButton>
          </List>
        </CardContent>
      </Card>

      {/* Stepper */}
      <Card sx={{ mb: 3 }}>
        <CardContent>
          <Typography variant="h6" gutterBottom>
            {__('Stepper', 'flux-plugins-common')}
          </Typography>
          <Stepper activeStep={1}>
            <Step>
              <StepLabel>{__('Step 1', 'flux-plugins-common')}</StepLabel>
            </Step>
            <Step>
              <StepLabel>{__('Step 2', 'flux-plugins-common')}</StepLabel>
            </Step>
            <Step>
              <StepLabel>{__('Step 3', 'flux-plugins-common')}</StepLabel>
            </Step>
          </Stepper>
        </CardContent>
      </Card>

      {/* Breadcrumbs */}
      <Card sx={{ mb: 3 }}>
        <CardContent>
          <Typography variant="h6" gutterBottom>
            {__('Breadcrumbs', 'flux-plugins-common')}
          </Typography>
          <Breadcrumbs>
            <Link color="inherit" href="#">
              {__('Home', 'flux-plugins-common')}
            </Link>
            <Link color="inherit" href="#">
              {__('Category', 'flux-plugins-common')}
            </Link>
            <Typography color="text.primary">{__('Current Page', 'flux-plugins-common')}</Typography>
          </Breadcrumbs>
        </CardContent>
      </Card>

      {/* Avatar & Skeleton */}
      <Card sx={{ mb: 3 }}>
        <CardContent>
          <Typography variant="h6" gutterBottom>
            {__('Avatar & Skeleton', 'flux-plugins-common')}
          </Typography>
          <Stack spacing={2} direction="row" alignItems="center" flexWrap="wrap">
            <Avatar>A</Avatar>
            <Avatar><Person /></Avatar>
            <Skeleton variant="circular" width={40} height={40} />
            <Skeleton variant="rectangular" width={200} height={60} />
            <Skeleton variant="text" width={150} />
          </Stack>
        </CardContent>
      </Card>

      {/* Dialog */}
      <Card sx={{ mb: 3 }}>
        <CardContent>
          <Typography variant="h6" gutterBottom>
            {__('Dialog', 'flux-plugins-common')}
          </Typography>
          <Button variant="contained" onClick={() => setDialogOpen(true)}>
            {__('Open Dialog', 'flux-plugins-common')}
          </Button>
          <Dialog open={dialogOpen} onClose={() => setDialogOpen(false)}>
            <DialogTitle>{__('Dialog Title', 'flux-plugins-common')}</DialogTitle>
            <DialogContent>
              <Typography>{__('Dialog content text', 'flux-plugins-common')}</Typography>
            </DialogContent>
            <DialogActions>
              <Button onClick={() => setDialogOpen(false)}>{__('Cancel', 'flux-plugins-common')}</Button>
              <Button variant="contained" onClick={() => setDialogOpen(false)}>{__('Confirm', 'flux-plugins-common')}</Button>
            </DialogActions>
          </Dialog>
        </CardContent>
      </Card>

      {/* Pagination */}
      <Card sx={{ mb: 3 }}>
        <CardContent>
          <Typography variant="h6" gutterBottom>
            {__('Pagination', 'flux-plugins-common')}
          </Typography>
          <Pagination count={10} color="primary" />
        </CardContent>
      </Card>

      {/* Tooltips */}
      <Card sx={{ mb: 3 }}>
        <CardContent>
          <Typography variant="h6" gutterBottom>
            {__('Tooltips', 'flux-plugins-common')}
          </Typography>
          <Stack spacing={2} direction="row">
            <Tooltip title={__('Tooltip text', 'flux-plugins-common')}>
              <Button>{__('Hover me', 'flux-plugins-common')}</Button>
            </Tooltip>
            <Tooltip title={__('Another tooltip', 'flux-plugins-common')}>
              <IconButton><Info /></IconButton>
            </Tooltip>
          </Stack>
        </CardContent>
      </Card>

      {/* Dividers */}
      <Card sx={{ mb: 3 }}>
        <CardContent>
          <Typography variant="h6" gutterBottom>
            {__('Dividers', 'flux-plugins-common')}
          </Typography>
          <Typography>{__('Content above divider', 'flux-plugins-common')}</Typography>
          <Divider sx={{ my: 2 }} />
          <Typography>{__('Content below divider', 'flux-plugins-common')}</Typography>
        </CardContent>
      </Card>
    </Box>
  );
};

export default StyleShowcase;

